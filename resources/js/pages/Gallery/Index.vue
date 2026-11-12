<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Heart,
    Image as ImageIcon,
    Monitor,
    Moon,
    Sun,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { subscribeToMercure } from '@/echo';
import MediaViewer from '@/components/MediaViewer.vue';
import ScanProgressBar from '@/components/ScanProgressBar.vue';
import SearchBar from '@/components/SearchBar.vue';
import TimelineGrid, { type MediaItem } from '@/components/TimelineGrid.vue';

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
    searchQuery?: string;
}>();

const page = usePage();
const { appearance, updateAppearance } = useAppearance();
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

function cycleTheme() {
    if (appearance.value === 'system') {
        updateAppearance('light');
    } else if (appearance.value === 'light') {
        updateAppearance('dark');
    } else {
        updateAppearance('system');
    }
}

const themeTitle = computed(() => {
    if (appearance.value === 'system') return 'Theme: Auto (System)';
    if (appearance.value === 'light') return 'Theme: Light';
    return 'Theme: Dark';
});

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
    <Head title="Gallery" />

    <div class="min-h-screen bg-background text-foreground transition-colors duration-200">
        <!-- Top bar: Natural scroll (non-sticky), minimal chrome, NO logo -->
        <header class="w-full border-b border-border/50 bg-background/90 transition-colors">
            <div class="mx-auto flex items-center justify-between gap-3 px-3 py-2.5 sm:px-6">
                <!-- SearchBar takes primary left/center stage -->
                <div class="flex-1 max-w-md">
                    <SearchBar :initial-query="props.searchQuery" />
                </div>

                <!-- Right Utility Actions -->
                <div class="flex items-center gap-1.5 sm:gap-2">
                    <!-- Favorites Filter Pill -->
                    <button
                        type="button"
                        class="flex h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium transition-all"
                        :class="
                            isFavoriteFilter
                                ? 'border-rose-500/40 bg-rose-500/10 text-rose-500 dark:text-rose-400 font-semibold shadow-xs'
                                : 'border-border/60 bg-muted/30 text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                        "
                        title="Filter by Favorites"
                        @click="toggleFavoriteFilter"
                    >
                        <Heart
                            class="size-3.5"
                            :class="isFavoriteFilter ? 'fill-current' : ''"
                        />
                        <span class="hidden sm:inline">Favorites</span>
                    </button>

                    <!-- Sort Selector -->
                    <select
                        class="h-8 rounded-lg border border-border/60 bg-muted/30 px-2 text-xs text-muted-foreground transition focus:border-ring focus:bg-background focus:text-foreground focus:outline-none"
                        :value="currentSort"
                        title="Sort media"
                        @change="changeSort"
                    >
                        <option value="captured_at_desc">Newest first</option>
                        <option value="captured_at_asc">Oldest first</option>
                        <option value="mtime_desc">Modified (newest)</option>
                        <option value="mtime_asc">Modified (oldest)</option>
                    </select>

                    <!-- Theme Switcher (Auto / Light / Dark) -->
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-border/60 bg-muted/30 text-muted-foreground transition hover:bg-muted/70 hover:text-foreground"
                        :title="themeTitle"
                        @click="cycleTheme"
                    >
                        <Monitor v-if="appearance === 'system'" class="size-3.5" />
                        <Sun v-else-if="appearance === 'light'" class="size-3.5" />
                        <Moon v-else class="size-3.5" />
                    </button>
                </div>
            </div>

            <!-- Integrated Hairline Scan Progress -->
            <ScanProgressBar
                :scanned="scanProgress.scanned"
                :total="scanProgress.total"
                :is-completed="scanProgress.isCompleted"
            />
        </header>

        <!-- Gallery Hero Content -->
        <main class="w-full px-2 py-4 sm:px-6 sm:py-6">
            <!-- Active Search Filter Badge -->
            <div
                v-if="props.searchQuery"
                class="mb-4 flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs text-primary"
            >
                <div class="flex items-center gap-1.5 truncate">
                    <span class="text-muted-foreground">Results for</span>
                    <strong class="font-medium text-foreground">“{{ props.searchQuery }}”</strong>
                    <span class="text-muted-foreground">({{ items.length }})</span>
                </div>
                <a
                    href="/"
                    class="flex items-center gap-1 font-medium hover:underline text-foreground ml-2"
                >
                    <X class="size-3" />
                    <span>Clear</span>
                </a>
            </div>

            <!-- Timeline Stream -->
            <TimelineGrid
                v-if="items.length > 0"
                :items="items"
                @select="openViewer"
            />

            <!-- Quiet Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-24 text-center text-muted-foreground"
            >
                <div class="mb-3 flex size-12 items-center justify-center rounded-2xl bg-muted/60">
                    <ImageIcon class="size-6 text-muted-foreground/60 stroke-[1.5]" />
                </div>
                <h3 class="text-sm font-semibold text-foreground">
                    No media found
                </h3>
                <p class="mt-1 text-xs text-muted-foreground/80 max-w-sm">
                    Photos and videos added to your storage library will automatically appear here.
                </p>
            </div>
        </main>

        <!-- MediaViewer Lightbox with Scrubber -->
        <MediaViewer
            :item="selectedItem"
            :items="items"
            :is-open="isViewerOpen"
            :has-next="hasNext"
            :has-prev="hasPrev"
            @close="closeViewer"
            @next="nextMedia"
            @prev="prevMedia"
            @select="openViewer"
        />
    </div>
</template>
