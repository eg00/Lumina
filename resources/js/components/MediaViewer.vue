<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { MediaItem } from './TimelineGrid.vue';

const props = defineProps<{
    item: MediaItem | null;
    isOpen: boolean;
    hasNext?: boolean;
    hasPrev?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'next'): void;
    (e: 'prev'): void;
}>();

const showInfo = ref(true);
const copied = ref(false);

function toggleInfo() {
    showInfo.value = !showInfo.value;
}

async function copyShareUrl() {
    if (!props.item) return;
    const shareUrl = `${window.location.origin}/view/${props.item.id}`;
    try {
        await navigator.clipboard.writeText(shareUrl);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        const textarea = document.createElement('textarea');
        textarea.value = shareUrl;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (!props.isOpen) return;

    if (e.key === 'Escape') emit('close');
    if (e.key === 'ArrowRight' && props.hasNext) emit('next');
    if (e.key === 'ArrowLeft' && props.hasPrev) emit('prev');
    if (e.key === 'i' || e.key === 'I') toggleInfo();
}

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeydown);
        } else {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', handleKeydown);
        }
    },
);

onMounted(() => {
    if (props.isOpen) {
        window.addEventListener('keydown', handleKeydown);
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
});

const formattedDate = computed(() => {
    if (!props.item?.captured_at && !props.item?.mtime) return '';
    const date = new Date(props.item.captured_at || props.item.mtime);
    return isNaN(date.getTime())
        ? ''
        : new Intl.DateTimeFormat('en-US', {
              dateStyle: 'long',
              timeStyle: 'medium',
          }).format(date);
});

const fileName = computed(() => {
    if (!props.item?.file_path) return '';
    return props.item.file_path.split('/').pop() || props.item.file_path;
});

const formattedSize = computed(() => {
    if (!props.item?.file_size) return null;
    const mb = props.item.file_size / 1024 / 1024;
    return mb >= 1
        ? `${mb.toFixed(2)} MB`
        : `${Math.round(props.item.file_size / 1024)} KB`;
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen && item"
            class="fixed inset-0 z-50 flex flex-col bg-black/95 text-slate-100 backdrop-blur-md"
        >
            <!-- Top Toolbar -->
            <header
                class="z-20 flex h-14 shrink-0 items-center justify-between border-b border-slate-800/80 bg-slate-950/80 px-4"
            >
                <div class="flex items-center gap-3 truncate">
                    <span class="truncate text-sm font-semibold text-white">{{
                        fileName
                    }}</span>
                    <span
                        v-if="item.media_type === 'video'"
                        class="rounded bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-300"
                    >
                        VIDEO
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Share Button -->
                    <button
                        type="button"
                        class="relative flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white"
                        title="Copy photo link"
                        @click="copyShareUrl"
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
                                d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                            />
                        </svg>
                        <span>{{ copied ? 'Copied!' : 'Share' }}</span>
                    </button>

                    <!-- Info Sidebar Toggle Button -->
                    <button
                        type="button"
                        class="flex items-center justify-center rounded-lg border p-1.5 text-xs font-medium transition"
                        :class="
                            showInfo
                                ? 'border-indigo-500 bg-indigo-600/20 text-indigo-300'
                                : 'border-slate-700 bg-slate-800/80 text-slate-300 hover:bg-slate-700 hover:text-white'
                        "
                        title="File information (I)"
                        @click="toggleInfo"
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
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </button>

                    <!-- Close Button -->
                    <button
                        type="button"
                        class="flex items-center justify-center rounded-lg border border-slate-700 bg-slate-800/80 p-1.5 text-slate-300 transition hover:bg-slate-700 hover:text-white"
                        aria-label="Close (Esc)"
                        @click="emit('close')"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </header>

            <!-- Main Content Area: Media Viewport + Optional Right Sidebar -->
            <div class="relative flex flex-1 overflow-hidden">
                <!-- Media Viewport -->
                <div
                    class="relative flex flex-1 items-center justify-center overflow-hidden p-4"
                >
                    <!-- Prev Button -->
                    <button
                        v-if="hasPrev"
                        type="button"
                        class="absolute left-4 z-20 rounded-full bg-slate-900/80 p-3 text-slate-300 shadow-xl backdrop-blur transition hover:bg-slate-800 hover:text-white"
                        aria-label="Previous"
                        @click.stop="emit('prev')"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                    </button>

                    <!-- Next Button -->
                    <button
                        v-if="hasNext"
                        type="button"
                        class="absolute right-4 z-20 rounded-full bg-slate-900/80 p-3 text-slate-300 shadow-xl backdrop-blur transition hover:bg-slate-800 hover:text-white"
                        aria-label="Next"
                        @click.stop="emit('next')"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>
                    </button>

                    <!-- Video Player -->
                    <video
                        v-if="item.media_type === 'video'"
                        controls
                        autoplay
                        playsinline
                        class="max-h-[82vh] max-w-[95%] rounded-lg shadow-2xl"
                        :src="'/media/' + item.id + '/stream'"
                    ></video>

                    <!-- Image Display -->
                    <img
                        v-else
                        :src="
                            '/storage/' +
                            (item.preview_path || item.thumbnail_path)
                        "
                        :alt="item.file_path"
                        class="max-h-[82vh] max-w-[95%] rounded-lg object-contain shadow-2xl transition-all"
                    />
                </div>

                <!-- Right-side Info Sidebar -->
                <aside
                    v-if="showInfo"
                    class="flex w-80 shrink-0 flex-col gap-6 overflow-y-auto border-l border-slate-800 bg-slate-900/95 p-6 text-xs text-slate-300 shadow-2xl backdrop-blur transition-all md:w-96"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-800 pb-3"
                    >
                        <h3
                            class="text-sm font-semibold tracking-wider text-slate-200 uppercase"
                        >
                            Information
                        </h3>
                        <button
                            type="button"
                            class="text-slate-400 hover:text-white"
                            title="Hide panel"
                            @click="showInfo = false"
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
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <!-- File Details -->
                    <div class="flex flex-col gap-2.5">
                        <span
                            class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                            >File</span
                        >
                        <div
                            class="flex flex-col gap-1 rounded-lg border border-slate-800 bg-slate-950/50 p-3"
                        >
                            <span class="font-medium break-all text-white">{{
                                fileName
                            }}</span>
                            <span
                                class="truncate text-[11px] break-all text-slate-400"
                                :title="item.file_path"
                                >{{ item.file_path }}</span
                            >
                            <div
                                class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-400"
                            >
                                <span v-if="formattedSize">{{
                                    formattedSize
                                }}</span>
                                <span v-if="item.mime_type"
                                    >• {{ item.mime_type }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- EXIF / Capture Details -->
                    <div class="flex flex-col gap-2.5">
                        <span
                            class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                            >Capture Details</span
                        >
                        <div
                            class="flex flex-col gap-2 rounded-lg border border-slate-800 bg-slate-950/50 p-3"
                        >
                            <div
                                v-if="formattedDate"
                                class="flex items-center justify-between"
                            >
                                <span class="text-slate-400">Date</span>
                                <span class="text-white">{{
                                    formattedDate
                                }}</span>
                            </div>
                            <div
                                v-if="item.width && item.height"
                                class="flex items-center justify-between"
                            >
                                <span class="text-slate-400">Resolution</span>
                                <span class="text-white"
                                    >{{ item.width }} × {{ item.height }}</span
                                >
                            </div>
                            <div
                                v-if="item.dominant_color"
                                class="flex items-center justify-between"
                            >
                                <span class="text-slate-400">Color</span>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-3 w-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                item.dominant_color,
                                        }"
                                    ></span>
                                    <span
                                        class="font-mono text-white uppercase"
                                        >{{ item.dominant_color }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Geolocation -->
                    <div
                        v-if="item.place_name || item.latitude"
                        class="flex flex-col gap-2.5"
                    >
                        <span
                            class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                            >Location</span
                        >
                        <div
                            class="flex flex-col gap-2 rounded-lg border border-slate-800 bg-slate-950/50 p-3"
                        >
                            <span
                                v-if="item.place_name"
                                class="font-medium text-white"
                                >📍 {{ item.place_name }}</span
                            >
                            <div
                                v-if="item.latitude && item.longitude"
                                class="flex items-center justify-between text-[11px]"
                            >
                                <span class="text-slate-400"
                                    >{{ item.latitude.toFixed(5) }},
                                    {{ item.longitude.toFixed(5) }}</span
                                >
                                <a
                                    :href="`https://www.google.com/maps?q=${item.latitude},${item.longitude}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-indigo-400 underline hover:text-indigo-300"
                                >
                                    View on map ↗
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div
                        v-if="item.tags && item.tags.length > 0"
                        class="flex flex-col gap-2.5"
                    >
                        <span
                            class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase"
                            >Tags</span
                        >
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in item.tags"
                                :key="tag.id"
                                class="rounded-md border border-indigo-500/30 bg-indigo-500/20 px-2 py-0.5 text-xs text-indigo-300"
                            >
                                #{{ tag.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div
                        class="mt-auto flex flex-col gap-2 border-t border-slate-800 pt-4"
                    >
                        <a
                            :href="'/media/' + item.id + '/stream'"
                            target="_blank"
                            class="flex items-center justify-center gap-2 rounded-lg bg-slate-800 py-2 font-medium text-slate-200 transition hover:bg-slate-700 hover:text-white"
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
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                />
                            </svg>
                            Open original
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </Teleport>
</template>
