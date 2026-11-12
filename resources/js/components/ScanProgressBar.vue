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
        class="fixed inset-x-0 top-0 z-40 border-b border-slate-700 bg-slate-900/95 px-4 py-2.5 text-white shadow-lg backdrop-blur"
    >
        <div class="mx-auto flex max-w-7xl flex-col gap-1.5">
            <div
                class="flex items-center justify-between text-xs font-medium text-slate-300"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-indigo-400"
                    ></span>
                    Indexing...
                </span>
                <span>{{ scanned }} / {{ total }} ({{ percentage }}%)</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
                <div
                    class="h-full rounded-full bg-indigo-500 transition-all duration-300"
                    :style="{ width: `${percentage}%` }"
                ></div>
            </div>
        </div>
    </div>
</template>
