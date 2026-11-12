<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        initialQuery?: string;
        placeholder?: string;
    }>(),
    {
        initialQuery: '',
        placeholder: 'Search photos by description, location or tags...',
    },
);

const query = ref(props.initialQuery);
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
        debounceTimer = setTimeout(run, 400);
    }
}

function clearSearch() {
    query.value = '';
    executeSearch(true);
}

function onInput() {
    executeSearch(false);
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        executeSearch(true);
    }
}

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});
</script>
<template>
    <div class="relative w-full max-w-md">
        <div
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"
        >
            <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>

        <input
            v-model="query"
            type="search"
            class="w-full rounded-lg border border-slate-800 bg-slate-900/90 py-1.5 pr-8 pl-9 text-xs text-slate-100 placeholder-slate-500 transition focus:border-indigo-500 focus:bg-slate-900 focus:outline-none"
            :placeholder="placeholder"
            @input="onInput"
            @keydown="onKeydown"
        />

        <button
            v-if="query"
            type="button"
            class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 transition hover:text-white"
            title="Clear"
            @click="clearSearch"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                <path
                    fill-rule="evenodd"
                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>
    </div>
</template>
