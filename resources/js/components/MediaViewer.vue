<script setup lang="ts">
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Copy,
    Download,
    Info,
    MapPin,
    Play,
    Share2,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import type { MediaItem } from './TimelineGrid.vue';

const props = defineProps<{
    item: MediaItem | null;
    items?: MediaItem[];
    isOpen: boolean;
    hasNext?: boolean;
    hasPrev?: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'next'): void;
    (e: 'prev'): void;
    (e: 'select', item: MediaItem): void;
}>();

const showInfo = ref(false);
const copied = ref(false);
const isChromeVisible = ref(true);
let hideTimer: ReturnType<typeof setTimeout> | null = null;

// Touch tracking
let touchStartX = 0;
let touchStartY = 0;

function resetHideTimer() {
    isChromeVisible.value = true;
    if (hideTimer) {
        clearTimeout(hideTimer);
    }
    // Don't auto-hide if info panel is open
    if (!showInfo.value) {
        hideTimer = setTimeout(() => {
            isChromeVisible.value = false;
        }, 2800);
    }
}

function toggleInfo() {
    showInfo.value = !showInfo.value;
    if (showInfo.value) {
        isChromeVisible.value = true;
        if (hideTimer) clearTimeout(hideTimer);
    } else {
        resetHideTimer();
    }
}

async function copyShareUrl() {
    if (!props.item) return;
    const shareUrl = `${window.location.origin}/view/${props.item.uuid}`;
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

    if (e.key === 'Escape') {
        if (showInfo.value) {
            showInfo.value = false;
        } else {
            emit('close');
        }
    }
    if (e.key === 'ArrowRight' && props.hasNext) emit('next');
    if (e.key === 'ArrowLeft' && props.hasPrev) emit('prev');
    if (e.key === 'i' || e.key === 'I') toggleInfo();
}

function handleTouchStart(e: TouchEvent) {
    if (e.touches.length === 1) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        resetHideTimer();
    }
}

function handleTouchEnd(e: TouchEvent) {
    if (e.changedTouches.length === 1) {
        const deltaX = e.changedTouches[0].clientX - touchStartX;
        const deltaY = e.changedTouches[0].clientY - touchStartY;
        const absX = Math.abs(deltaX);
        const absY = Math.abs(deltaY);

        // Swipe down to dismiss
        if (deltaY > 90 && absY > absX * 1.5) {
            emit('close');
            return;
        }

        // Horizontal swipe to navigate
        if (absX > 50 && absX > absY) {
            if (deltaX < 0 && props.hasNext) {
                emit('next');
            } else if (deltaX > 0 && props.hasPrev) {
                emit('prev');
            }
        }
    }
}

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            document.body.style.overflow = 'hidden';
            window.addEventListener('keydown', handleKeydown);
            resetHideTimer();
        } else {
            document.body.style.overflow = '';
            window.removeEventListener('keydown', handleKeydown);
            if (hideTimer) clearTimeout(hideTimer);
            showInfo.value = false;
        }
    },
);

onMounted(() => {
    if (props.isOpen) {
        window.addEventListener('keydown', handleKeydown);
        resetHideTimer();
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', handleKeydown);
    if (hideTimer) clearTimeout(hideTimer);
});

const formattedDate = computed(() => {
    if (!props.item?.captured_at && !props.item?.mtime) return '';
    const date = new Date(props.item.captured_at || props.item.mtime);
    return isNaN(date.getTime())
        ? ''
        : new Intl.DateTimeFormat('en-US', {
              dateStyle: 'medium',
              timeStyle: 'short',
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
        ? `${mb.toFixed(1)} MB`
        : `${Math.round(props.item.file_size / 1024)} KB`;
});

const cameraName = computed(() => {
    if (!props.item) return null;
    const make = props.item.camera_make?.trim() || '';
    const model = props.item.camera_model?.trim() || '';
    if (!make && !model) return null;
    if (model.toLowerCase().startsWith(make.toLowerCase())) {
        return model;
    }
    return `${make} ${model}`.trim();
});

const hasShootingData = computed(() => {
    if (!props.item) return false;
    return !!(
        cameraName.value ||
        props.item.lens_model ||
        props.item.f_number ||
        props.item.exposure_time ||
        props.item.iso ||
        props.item.focal_length
    );
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen && item"
            class="fixed inset-0 z-[9999] flex flex-col overflow-hidden bg-black/92 text-white backdrop-blur-xl select-none"
            @mousemove="resetHideTimer"
            @touchstart="handleTouchStart"
            @touchend="handleTouchEnd"
        >
            <!-- Floating Top Quiet Bar -->
            <header
                class="pointer-events-none fixed inset-x-0 top-0 z-30 flex items-center justify-between p-3 transition-opacity duration-300 sm:p-5"
                :class="{
                    'opacity-100': isChromeVisible || showInfo,
                    'opacity-0': !isChromeVisible && !showInfo,
                }"
            >
                <!-- File name capsule -->
                <div
                    class="pointer-events-auto flex items-center gap-2 rounded-full border border-white/10 bg-black/50 px-3 py-1.5 shadow-lg backdrop-blur-md"
                >
                    <span
                        class="max-w-[180px] truncate text-xs font-medium text-white/90 sm:max-w-xs"
                    >
                        {{ fileName }}
                    </span>
                    <span
                        v-if="item.media_type === 'video'"
                        class="py-0.2 rounded-full bg-white/15 px-1.5 text-[9px] font-semibold tracking-wider text-white uppercase"
                    >
                        Video
                    </span>
                </div>

                <!-- Action buttons capsule -->
                <div
                    class="pointer-events-auto flex items-center gap-1.5 rounded-full border border-white/10 bg-black/50 p-1 shadow-lg backdrop-blur-md"
                >
                    <!-- Similar Photos -->
                    <a
                        :href="'/media/' + item.uuid + '/similar'"
                        class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-white/80 transition hover:bg-white/10 hover:text-white"
                        title="Find visually similar photos"
                    >
                        <Sparkles class="size-3.5" />
                        <span class="hidden sm:inline">Similar</span>
                    </a>

                    <!-- Share -->
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-white/80 transition hover:bg-white/10 hover:text-white"
                        :title="copied ? 'Copied link!' : 'Share photo link'"
                        @click="copyShareUrl"
                    >
                        <Copy v-if="copied" class="size-3.5 text-emerald-400" />
                        <Share2 v-else class="size-3.5" />
                        <span class="hidden sm:inline">{{
                            copied ? 'Copied' : 'Share'
                        }}</span>
                    </button>

                    <!-- Info toggle -->
                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-full transition"
                        :class="
                            showInfo
                                ? 'bg-white text-black'
                                : 'text-white/80 hover:bg-white/10 hover:text-white'
                        "
                        title="Info (I)"
                        @click="toggleInfo"
                    >
                        <Info class="size-3.5" />
                    </button>

                    <!-- Close -->
                    <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded-full text-white/80 transition hover:bg-white/15 hover:text-white"
                        title="Close (Esc)"
                        @click="emit('close')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </header>

            <!-- Main Stage -->
            <div
                class="relative flex flex-1 items-center justify-center overflow-hidden"
            >
                <!-- Prev Button -->
                <button
                    v-if="hasPrev"
                    type="button"
                    class="pointer-events-auto absolute left-3 z-20 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-black/40 text-white/80 shadow-2xl backdrop-blur-md transition-all duration-300 hover:scale-105 hover:bg-black/70 hover:text-white sm:left-6 sm:h-12 sm:w-12"
                    :class="{
                        'opacity-100': isChromeVisible || showInfo,
                        'opacity-0': !isChromeVisible && !showInfo,
                    }"
                    aria-label="Previous (←)"
                    @click.stop="emit('prev')"
                >
                    <ChevronLeft class="size-5 stroke-2 sm:size-6" />
                </button>

                <!-- Next Button -->
                <button
                    v-if="hasNext"
                    type="button"
                    class="pointer-events-auto absolute right-3 z-20 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 bg-black/40 text-white/80 shadow-2xl backdrop-blur-md transition-all duration-300 hover:scale-105 hover:bg-black/70 hover:text-white sm:h-12 sm:w-12"
                    :class="[
                        isChromeVisible || showInfo
                            ? 'opacity-100'
                            : 'pointer-events-none opacity-0',
                        showInfo ? 'sm:right-6 md:right-94' : 'sm:right-6',
                    ]"
                    aria-label="Next (→)"
                    @click.stop="emit('next')"
                >
                    <ChevronRight class="size-5 stroke-2 sm:size-6" />
                </button>

                <!-- Media Display with Cinematic Transition -->
                <div
                    class="relative flex h-full w-full items-center justify-center p-2 sm:p-8"
                    @click="resetHideTimer"
                >
                    <Transition name="media-fade" mode="out-in">
                        <!-- Video Player -->
                        <video
                            v-if="item.media_type === 'video'"
                            :key="'video-' + item.uuid"
                            controls
                            autoplay
                            playsinline
                            class="max-h-[85vh] max-w-[96vw] rounded-lg shadow-2xl outline-none"
                            :src="'/media/' + item.uuid + '/stream'"
                        ></video>

                        <!-- Image Display -->
                        <img
                            v-else
                            :key="'image-' + item.uuid"
                            :src="
                                '/storage/' +
                                (item.preview_path || item.thumbnail_path)
                            "
                            :alt="item.file_path"
                            class="max-h-[85vh] max-w-[96vw] rounded-lg object-contain shadow-2xl select-none"
                        />
                    </Transition>
                </div>

                <!-- Bottom Scrubber Strip (Context miniature carousel) -->
                <div
                    v-if="props.items && props.items.length > 1"
                    class="pointer-events-none fixed inset-x-0 bottom-4 z-20 flex justify-center px-4 transition-opacity duration-300"
                    :class="{
                        'opacity-100': isChromeVisible && !showInfo,
                        'opacity-0': !isChromeVisible || showInfo,
                    }"
                >
                    <div
                        class="pointer-events-auto flex max-w-[90vw] scrollbar-none items-center gap-1.5 overflow-x-auto rounded-2xl border border-white/10 bg-black/60 p-1.5 shadow-2xl backdrop-blur-lg sm:max-w-2xl"
                    >
                        <button
                            v-for="subItem in props.items.slice(0, 40)"
                            :key="subItem.uuid"
                            type="button"
                            class="relative h-9 w-9 shrink-0 overflow-hidden rounded-md transition-all duration-200"
                            :class="
                                subItem.uuid === item.uuid
                                    ? 'z-10 scale-110 ring-2 ring-white'
                                    : 'opacity-50 hover:opacity-100'
                            "
                            @click.stop="emit('select', subItem)"
                        >
                            <img
                                v-if="subItem.thumbnail_path"
                                :src="'/storage/' + subItem.thumbnail_path"
                                :alt="subItem.file_path"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="h-full w-full"
                                :style="{
                                    backgroundColor:
                                        subItem.dominant_color || '#333',
                                }"
                            ></div>
                        </button>
                    </div>
                </div>

                <!-- Right-side Info Drawer (Desktop) & Bottom Sheet (Mobile) -->
                <aside
                    v-if="showInfo"
                    class="fixed inset-x-0 bottom-0 z-40 flex max-h-[80vh] w-full shrink-0 flex-col gap-5 overflow-y-auto rounded-t-3xl border-t border-white/10 bg-slate-950/95 p-5 text-xs text-slate-300 shadow-2xl backdrop-blur-2xl transition-all sm:p-6 md:static md:max-h-full md:w-88 md:rounded-none md:border-t-0 md:border-l"
                >
                    <!-- Mobile drag handle -->
                    <div
                        class="mx-auto h-1 w-10 shrink-0 rounded-full bg-white/20 md:hidden"
                    ></div>

                    <div
                        class="flex items-center justify-between border-b border-white/10 pb-3"
                    >
                        <h3
                            class="text-xs font-semibold tracking-wider text-white uppercase"
                        >
                            Information
                        </h3>
                        <button
                            type="button"
                            class="rounded-full p-1 text-slate-400 transition hover:bg-white/10 hover:text-white"
                            title="Close info panel (Esc)"
                            @click="showInfo = false"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- File Details Card -->
                    <div class="flex flex-col gap-2">
                        <span
                            class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            File
                        </span>
                        <div
                            class="flex flex-col gap-1.5 rounded-xl border border-white/5 bg-white/[0.03] p-3"
                        >
                            <span class="font-medium break-all text-white">{{
                                fileName
                            }}</span>
                            <span
                                class="truncate text-[11px] text-slate-400"
                                :title="item.file_path"
                            >
                                {{ item.file_path }}
                            </span>
                            <div
                                class="mt-1 flex items-center gap-2 text-[11px] text-slate-400"
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

                    <!-- EXIF / Capture Details Card -->
                    <div class="flex flex-col gap-2">
                        <span
                            class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Capture Details
                        </span>
                        <div
                            class="flex flex-col gap-2.5 rounded-xl border border-white/5 bg-white/[0.03] p-3"
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
                                <span class="text-slate-400">Dimensions</span>
                                <span class="font-mono text-white"
                                    >{{ item.width }} × {{ item.height }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Camera & Shooting Parameters Card -->
                    <div v-if="hasShootingData" class="flex flex-col gap-2">
                        <span
                            class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Camera & Optics
                        </span>
                        <div
                            class="flex flex-col gap-2 rounded-xl border border-white/5 bg-white/[0.03] p-3"
                        >
                            <!-- Camera Model -->
                            <div
                                v-if="cameraName"
                                class="flex items-center gap-2 text-sm font-medium text-white"
                            >
                                <Camera class="size-4 shrink-0 text-sky-400" />
                                <span class="truncate">{{ cameraName }}</span>
                            </div>

                            <!-- Lens Model -->
                            <div
                                v-if="item.lens_model"
                                class="truncate text-xs text-slate-300"
                            >
                                {{ item.lens_model }}
                            </div>

                            <!-- Exposure Badges Grid -->
                            <div
                                v-if="
                                    item.f_number ||
                                    item.exposure_time ||
                                    item.iso ||
                                    item.focal_length
                                "
                                class="mt-1 grid grid-cols-4 gap-1 rounded-lg border border-white/5 bg-black/20 p-2 text-center"
                            >
                                <div v-if="item.f_number" class="flex flex-col">
                                    <span
                                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                                        >Aperture</span
                                    >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-200"
                                        >f/{{ item.f_number }}</span
                                    >
                                </div>
                                <div
                                    v-if="item.exposure_time"
                                    class="flex flex-col"
                                >
                                    <span
                                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                                        >Shutter</span
                                    >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-200"
                                        >{{ item.exposure_time }}s</span
                                    >
                                </div>
                                <div v-if="item.iso" class="flex flex-col">
                                    <span
                                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                                        >ISO</span
                                    >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-200"
                                        >{{ item.iso }}</span
                                    >
                                </div>
                                <div
                                    v-if="item.focal_length"
                                    class="flex flex-col"
                                >
                                    <span
                                        class="text-[9px] tracking-wider text-slate-400 uppercase"
                                        >Focal</span
                                    >
                                    <span
                                        class="font-mono text-xs font-semibold text-slate-200"
                                        >{{ item.focal_length }}mm</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Geolocation Card -->
                    <div
                        v-if="item.place_name || item.latitude"
                        class="flex flex-col gap-2"
                    >
                        <span
                            class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Location
                        </span>
                        <div
                            class="flex flex-col gap-2 rounded-xl border border-white/5 bg-white/[0.03] p-3"
                        >
                            <div
                                v-if="item.place_name"
                                class="flex items-center gap-1.5 font-medium text-white"
                            >
                                <MapPin
                                    class="size-3.5 shrink-0 text-rose-400"
                                />
                                <span>{{ item.place_name }}</span>
                            </div>
                            <div
                                v-if="item.latitude && item.longitude"
                                class="flex items-center justify-between pt-1 text-[11px]"
                            >
                                <span class="font-mono text-slate-400">
                                    {{ item.latitude.toFixed(4) }},
                                    {{ item.longitude.toFixed(4) }}
                                </span>
                                <a
                                    :href="`/map?lat=${item.latitude}&lng=${item.longitude}&zoom=16`"
                                    class="text-sky-400 underline transition hover:text-sky-300"
                                >
                                    Open in Map ↗
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Tags Card -->
                    <div
                        v-if="item.tags && item.tags.length > 0"
                        class="flex flex-col gap-2"
                    >
                        <span
                            class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Tags
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in item.tags"
                                :key="tag.id"
                                class="rounded-md border border-white/10 bg-white/5 px-2 py-0.5 text-xs text-slate-200"
                            >
                                #{{ tag.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div
                        class="mt-auto flex flex-col gap-2 border-t border-white/10 pt-4"
                    >
                        <a
                            :href="'/media/' + item.uuid + '/stream'"
                            target="_blank"
                            class="flex items-center justify-center gap-2 rounded-xl bg-white/10 py-2.5 font-medium text-white transition hover:bg-white/20"
                        >
                            <Download class="size-4" />
                            <span>Download / Original</span>
                        </a>
                    </div>
                </aside>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.media-fade-enter-active,
.media-fade-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}
.media-fade-enter-from {
    opacity: 0;
    transform: scale(0.985);
}
.media-fade-leave-to {
    opacity: 0;
    transform: scale(1.015);
}

.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
