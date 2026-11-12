<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    scanned: number;
    total: number;
    isCompleted?: boolean;
}>();

const percentage = computed(() => {
    if (!props.total || props.total <= 0) return 0;
    return Math.min(100, Math.round((props.scanned / props.total) * 100));
});
</script>

<template>
    <div
        v-if="total > 0 && !isCompleted"
        class="w-full transition-all duration-300"
    >
        <!-- Ultra-thin hairline progress bar -->
        <div class="h-[2px] w-full overflow-hidden bg-muted/40">
            <div
                class="h-full bg-primary transition-all duration-300 ease-out"
                :style="{ width: `${percentage}%` }"
            ></div>
        </div>

        <!-- Quiet status pill container -->
        <div
            class="mx-auto flex max-w-7xl items-center justify-between px-4 py-1.5 text-[11px] text-muted-foreground sm:px-6"
        >
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary/60 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-primary"
                    ></span>
                </span>
                <span>Indexing media library…</span>
            </div>
            <span class="font-mono tabular-nums"
                >{{ scanned }} / {{ total }} ({{ percentage }}%)</span
            >
        </div>
    </div>
</template>
