<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { subscribeToMercure } from '@/echo';
import ScanProgressBar from '@/components/ScanProgressBar.vue';
import TimelineGrid, { type MediaItem } from '@/components/TimelineGrid.vue';
import MediaViewer from '@/components/MediaViewer.vue';

interface PaginatedMedia {
    data: MediaItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
}

interface Features {
    ai_search: boolean;
    geo_map: boolean;
    tags: boolean;
    default_sort: string;
}

const props = defineProps<{
    media: PaginatedMedia;
    features: Features;
    mercure: {
        hubUrl: string;
    };
    activeMediaId?: number;
    activeMediaItem?: MediaItem;
}>();

const page = usePage();
const items = ref<MediaItem[]>([...props.media.data]);

watch(
    () => props.media.data,
    (newData) => {
        items.value = [...newData];
    },
    { deep: true },
);

// Scan progress state
const scanProgress = ref({
    scanned: 0,
    total: 0,
    isCompleted: true,
});

// Lightbox state (MediaViewer)
const selectedItem = ref<MediaItem | null>(null);
const isViewerOpen = ref(false);

const currentIndex = computed(() => {
    if (!selectedItem.value) return -1;
    return items.value.findIndex((i) => i.id === selectedItem.value!.id);
});

const hasNext = computed(
    () =>
        currentIndex.value >= 0 && currentIndex.value < items.value.length - 1,
);
const hasPrev = computed(() => currentIndex.value > 0);

function openViewer(item: MediaItem, updateUrl = true) {
    selectedItem.value = item;
    isViewerOpen.value = true;
    if (updateUrl && typeof window !== 'undefined') {
        window.history.pushState({ mediaId: item.id }, '', `/view/${item.id}`);
    }
}

function closeViewer(updateUrl = true) {
    isViewerOpen.value = false;
    selectedItem.value = null;
    if (
        updateUrl &&
        typeof window !== 'undefined' &&
        window.location.pathname.startsWith('/view/')
    ) {
        window.history.pushState({}, '', '/');
    }
}

function nextMedia() {
    if (hasNext.value) {
        selectedItem.value = items.value[currentIndex.value + 1];
        if (typeof window !== 'undefined' && selectedItem.value) {
            window.history.replaceState(
                { mediaId: selectedItem.value.id },
                '',
                `/view/${selectedItem.value.id}`,
            );
        }
    }
}

function prevMedia() {
    if (hasPrev.value) {
        selectedItem.value = items.value[currentIndex.value - 1];
        if (typeof window !== 'undefined' && selectedItem.value) {
            window.history.replaceState(
                { mediaId: selectedItem.value.id },
                '',
                `/view/${selectedItem.value.id}`,
            );
        }
    }
}

function onPopState() {
    if (typeof window === 'undefined') return;
    const match = window.location.pathname.match(/\/view\/(\d+)/);
    if (match) {
        const id = parseInt(match[1], 10);
        const found = items.value.find((i) => i.id === id);
        if (found) {
            openViewer(found, false);
        }
    } else {
        closeViewer(false);
    }
}

const currentSort = computed(() => {
    const search = page.url.split('?')[1] || '';
    return (
        new URLSearchParams(search).get('sort') || props.features.default_sort
    );
});

const isFavoriteFilter = computed(() => {
    const search = page.url.split('?')[1] || '';
    return new URLSearchParams(search).get('favorite') === '1';
});

function toggleFavoriteFilter() {
    const search = page.url.split('?')[1] || '';
    const params = new URLSearchParams(search);
    if (isFavoriteFilter.value) {
        params.delete('favorite');
    } else {
        params.set('favorite', '1');
    }
    const query = params.toString();
    const pathname = page.url.split('?')[0];
    router.visit(query ? `${pathname}?${query}` : pathname, {
        preserveState: true,
    });
}

function changeSort(e: Event) {
    const sort = (e.target as HTMLSelectElement).value;
    const search = page.url.split('?')[1] || '';
    const params = new URLSearchParams(search);
    params.set('sort', sort);
    const pathname = page.url.split('?')[0];
    router.visit(`${pathname}?${params.toString()}`, { preserveState: true });
}

let eventSource: EventSource | null = null;

onMounted(() => {
    if (typeof window !== 'undefined') {
        window.addEventListener('popstate', onPopState);
    }

    if (props.activeMediaItem) {
        const existing = items.value.find(
            (i) => i.id === props.activeMediaItem!.id,
        );
        if (existing) {
            openViewer(existing, false);
        } else {
            items.value.unshift(props.activeMediaItem);
            openViewer(props.activeMediaItem, false);
        }
    } else if (props.activeMediaId) {
        const existing = items.value.find((i) => i.id === props.activeMediaId);
        if (existing) {
            openViewer(existing, false);
        }
    } else {
        onPopState();
    }

    eventSource = subscribeToMercure(props.mercure.hubUrl, {
        onProgress: (data) => {
            scanProgress.value = {
                scanned: data.scanned,
                total: data.total,
                isCompleted: data.isCompleted,
            };
        },
        onMediaProcessed: (data) => {
            const idx = items.value.findIndex((i) => i.id === data.mediaFileId);
            if (idx !== -1) {
                items.value[idx].thumbnail_path = data.thumbnailPath;
                items.value[idx].preview_path = data.previewPath;
                items.value[idx].dominant_color = data.dominantColor;
            } else {
                items.value.unshift({
                    id: data.mediaFileId,
                    file_path: '',
                    mime_type: 'image/jpeg',
                    media_type: 'image',
                    width: null,
                    height: null,
                    dominant_color: data.dominantColor,
                    thumbnail_path: data.thumbnailPath,
                    preview_path: data.previewPath,
                    captured_at: data.capturedAt,
                    mtime: new Date().toISOString(),
                    is_favorite: false,
                });
            }
        },
    });
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('popstate', onPopState);
    }
    eventSource?.close();
});
</script>

<template>
    <Head title="Lumina — Gallery" />

    <div class="min-h-screen bg-slate-950 text-slate-100">
        <ScanProgressBar
            :scanned="scanProgress.scanned"
            :total="scanProgress.total"
            :is-completed="scanProgress.isCompleted"
        />

        <header
            class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/80 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-full items-center justify-between px-4 py-3 sm:px-6"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-lg"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-white"
                        >Lumina</span
                    >
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                        :class="
                            isFavoriteFilter
                                ? 'border-rose-500 bg-rose-500/10 text-rose-400'
                                : 'border-slate-800 bg-slate-900 text-slate-300 hover:bg-slate-800'
                        "
                        @click="toggleFavoriteFilter"
                    >
                        <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24">
                            <path
                                d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                            />
                        </svg>
                        Favorites
                    </button>

                    <select
                        class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs text-slate-300 transition focus:border-indigo-500 focus:outline-none"
                        :value="currentSort"
                        @change="changeSort"
                    >
                        <option value="captured_at_desc">Newest first</option>
                        <option value="captured_at_asc">Oldest first</option>
                        <option value="mtime_desc">
                            Modified date (newest)
                        </option>
                        <option value="mtime_asc">
                            Modified date (oldest)
                        </option>
                    </select>

                    <!-- Admin Link -->
                    <a
                        href="/admin"
                        class="rounded-lg border border-slate-800 bg-slate-900 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-800 hover:text-white"
                    >
                        Admin Panel
                    </a>
                </div>
            </div>
        </header>

        <main class="max-auto mx-auto px-4 py-6 sm:px-6">
            <TimelineGrid
                v-if="items.length > 0"
                :items="items"
                @select="openViewer"
            />

            <div
                v-else
                class="flex flex-col items-center justify-center py-24 text-center text-slate-400"
            >
                <div class="mb-4 rounded-full bg-slate-900 p-4">
                    <svg
                        class="h-8 w-8 text-slate-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-slate-200">
                    No media files found
                </h3>
                <p class="mt-1 text-sm text-slate-400">
                    Add a media source and run a scan in the admin panel.
                </p>
                <a
                    href="/admin"
                    class="mt-4 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow transition hover:bg-indigo-500"
                >
                    Go to Admin Panel
                </a>
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
