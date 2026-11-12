<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        initialQuery?: string;
        placeholder?: string;
    }>(),
    {
        initialQuery: '',
        placeholder: 'Search photos, tags, locations…',
    },
);

const query = ref(props.initialQuery);
const inputRef = ref<HTMLInputElement | null>(null);
const isFocused = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.initialQuery,
    (val) => {
        query.value = val;
    },
);

function executeSearch(immediate = false) {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    const run = () => {
        const trimmed = query.value.trim();
        const currentUrl = new URL(window.location.href);
        const currentQ = currentUrl.searchParams.get('q') || '';

        if (trimmed === currentQ) return;

        if (trimmed === '') {
            if (currentUrl.pathname === '/search') {
                router.visit('/', { preserveState: true });
            } else {
                currentUrl.searchParams.delete('q');
                router.visit(currentUrl.pathname + currentUrl.search, {
                    preserveState: true,
                });
            }
        } else {
            router.visit(`/search?q=${encodeURIComponent(trimmed)}`, {
                preserveState: true,
            });
        }
    };

    if (immediate) {
        run();
    } else {
        debounceTimer = setTimeout(run, 350);
    }
}

function clearSearch() {
    query.value = '';
    executeSearch(true);
    inputRef.value?.focus();
}

function onInput() {
    executeSearch(false);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        executeSearch(true);
    } else if (e.key === 'Escape') {
        inputRef.value?.blur();
    }
}

function handleGlobalKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        inputRef.value?.focus();
        inputRef.value?.select();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <div class="relative w-full max-w-sm sm:max-w-md">
        <div
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-muted-foreground transition-colors"
            :class="{ 'text-foreground': isFocused }"
        >
            <Search class="size-3.5 stroke-[2]" />
        </div>

        <input
            ref="inputRef"
            v-model="query"
            type="search"
            class="h-8 w-full rounded-lg border border-border/60 bg-muted/40 py-1 pr-16 pl-8 text-xs text-foreground transition-all placeholder:text-muted-foreground/70 focus:border-ring focus:bg-background focus:ring-1 focus:ring-ring focus:outline-none"
            :placeholder="placeholder"
            @focus="isFocused = true"
            @blur="isFocused = false"
            @input="onInput"
            @keydown="onKeydown"
        />

        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
            <button
                v-if="query"
                type="button"
                class="flex h-5 w-5 items-center justify-center rounded text-muted-foreground transition hover:bg-muted hover:text-foreground"
                title="Clear (Esc)"
                @click="clearSearch"
            >
                <X class="size-3 stroke-[2.5]" />
            </button>

            <kbd
                v-else
                class="pointer-events-none hidden rounded border border-border/60 bg-background/80 px-1.5 font-mono text-[10px] font-medium text-muted-foreground/80 select-none sm:inline-block"
            >
                ⌘K
            </kbd>
        </div>
    </div>
</template>
