<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MediaViewer from '@/components/MediaViewer.vue';
import type { MediaItem } from '@/components/TimelineGrid.vue';

interface PaginatedSimilar {
    data: (MediaItem & { distance?: number })[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    targetMedia: MediaItem;
    media: PaginatedSimilar;
}>();

const selectedItem = ref<MediaItem | null>(null);
const isViewerOpen = ref(false);

const currentIndex = computed(() => {
    if (!selectedItem.value) return -1;
    return props.media.data.findIndex((i) => i.id === selectedItem.value!.id);
});

const hasNext = computed(
    () =>
        currentIndex.value >= 0 &&
        currentIndex.value < props.media.data.length - 1,
);
const hasPrev = computed(() => currentIndex.value > 0);

function openViewer(item: MediaItem) {
    selectedItem.value = item;
    isViewerOpen.value = true;
}

function closeViewer() {
    isViewerOpen.value = false;
    selectedItem.value = null;
}

function nextMedia() {
    if (hasNext.value) {
        selectedItem.value = props.media.data[currentIndex.value + 1];
    }
}

function prevMedia() {
    if (hasPrev.value) {
        selectedItem.value = props.media.data[currentIndex.value - 1];
    }
}

function formatSimilarity(distance?: number): string {
    if (distance === undefined || distance === null) return '';
    // Cosine distance: 0 = identical, 1 = orthogonal. Similarity %:
    const similarity = Math.max(
        0,
        Math.min(100, Math.round((1 - distance) * 100)),
    );
    return `${similarity}% match`;
}
</script>
<template>
    <Head
        :title="
            'Similar to ' +
            (targetMedia.file_path.split('/').pop() || 'photo') +
            ' — Lumina'
        "
    />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <header
            class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/80 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <Link
                        href="/"
                        class="flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
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
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                        Back to Gallery
                    </Link>
                    <span class="text-sm font-semibold text-slate-200"
                        >Visual Similarity Search</span
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <div
                class="mb-8 flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900/60 p-4 sm:flex-row sm:items-center sm:gap-6"
            >
                <div
                    class="relative h-28 w-28 shrink-0 overflow-hidden rounded-lg border border-slate-800 bg-slate-950"
                >
                    <img
                        :src="
                            '/storage/' +
                            (targetMedia.thumbnail_path ||
                                targetMedia.preview_path)
                        "
                        :alt="targetMedia.file_path"
                        class="h-full w-full object-cover"
                    />
                </div>
                <div class="flex flex-col gap-1 text-xs">
                    <span
                        class="text-[11px] font-semibold tracking-wider text-indigo-400 uppercase"
                        >Target Photo</span
                    >
                    <h2 class="text-base font-bold break-all text-white">
                        {{ targetMedia.file_path.split('/').pop() }}
                    </h2>
                    <span v-if="targetMedia.place_name" class="text-slate-400"
                        >📍 {{ targetMedia.place_name }}</span
                    >
                    <span class="text-slate-400">
                        Found {{ media.total }} visually similar
                        {{ media.total === 1 ? 'photo' : 'photos' }}
                    </span>
                </div>
            </div>

            <div
                v-if="media.data.length > 0"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            >
                <div
                    v-for="item in media.data"
                    :key="item.id"
                    class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg bg-slate-900 shadow transition hover:ring-2 hover:ring-indigo-500"
                    :style="{
                        backgroundColor: item.dominant_color || '#1e293b',
                    }"
                    @click="openViewer(item)"
                >
                    <img
                        v-if="item.thumbnail_path || item.preview_path"
                        :src="
                            '/storage/' +
                            (item.thumbnail_path || item.preview_path)
                        "
                        :alt="item.file_path"
                        loading="lazy"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    />

                    <div
                        v-if="item.distance !== undefined"
                        class="absolute top-2 left-2 rounded bg-black/75 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-400 backdrop-blur"
                    >
                        {{ formatSimilarity(item.distance) }}
                    </div>

                    <div
                        v-if="item.media_type === 'video'"
                        class="absolute top-2 right-2 rounded bg-black/60 p-1 text-white backdrop-blur"
                    >
                        <svg class="h-3 w-3 fill-current" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center py-20 text-center text-slate-400"
            >
                <p>No similar photos found.</p>
            </div>
        </main>

        <MediaViewer
            :item="selectedItem"
            :is-open="isViewerOpen"
            :has-next="hasNext"
            :has-prev="hasPrev"
            @close="closeViewer"
            @next="nextMedia"
            @prev="prevMedia"
        />
    </div>
</template>
