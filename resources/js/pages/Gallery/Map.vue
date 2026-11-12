<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import MediaViewer from '@/components/MediaViewer.vue';
import type { MediaItem } from '@/components/TimelineGrid.vue';

interface Features {
    ai_search: boolean;
    geo_map: boolean;
    tags: boolean;
    default_sort: string;
}

defineProps<{
    features: Features;
}>();

const mapContainer = ref<HTMLElement | null>(null);
let map: L.Map | null = null;
let markersLayer: L.LayerGroup | null = null;
let onMapClick: ((e: MouseEvent) => void) | null = null;

const markers = ref<MediaItem[]>([]);
const totalVisible = ref(0);
const selectedItem = ref<MediaItem | null>(null);
const isViewerOpen = ref(false);

const currentIndex = computed(() => {
    if (!selectedItem.value) return -1;
    return markers.value.findIndex((m) => m.uuid === selectedItem.value!.uuid);
});

const hasNext = computed(
    () =>
        currentIndex.value >= 0 &&
        currentIndex.value < markers.value.length - 1,
);
const hasPrev = computed(() => currentIndex.value > 0);

function nextMedia() {
    if (hasNext.value) {
        selectedItem.value = markers.value[currentIndex.value + 1];
    }
}

function prevMedia() {
    if (hasPrev.value) {
        selectedItem.value = markers.value[currentIndex.value - 1];
    }
}

async function loadMarkers() {
    if (!map) return;
    const bounds = map.getBounds();
    const boundsParam = `${bounds.getSouth()},${bounds.getWest()},${bounds.getNorth()},${bounds.getEast()}`;

    try {
        const response = await fetch(`/api/geo/markers?bounds=${boundsParam}`);
        if (!response.ok) return;

        const data: MediaItem[] = await response.json();
        markers.value = data;
        totalVisible.value = data.length;

        renderMarkers(data);
    } catch (e) {
        console.error('Failed to load map markers:', e);
    }
}

function renderMarkers(items: MediaItem[]) {
    if (!map || !markersLayer) return;
    markersLayer.clearLayers();

    for (const item of items) {
        if (!item.latitude || !item.longitude) continue;

        const thumbUrl =
            item.thumbnail_path || item.preview_path
                ? `/storage/${item.thumbnail_path || item.preview_path}`
                : '';

        const iconHtml = `
                <div class="relative group cursor-pointer">
                    <div class="h-10 w-10 overflow-hidden rounded-full border-2 border-indigo-500 bg-slate-900 shadow-xl transition-transform duration-200 hover:scale-125" style="border-color: ${item.dominant_color || '#6366f1'}">
                        <img src="${thumbUrl}" alt="" class="h-full w-full object-cover" />
                    </div>
                    ${item.media_type === 'video' ? '<span class="absolute -bottom-1 -right-1 rounded bg-black/80 px-1 text-[8px] font-bold text-white">▶</span>' : ''}
                </div>
            `;

        const customIcon = L.divIcon({
            html: iconHtml,
            className: 'custom-photo-marker',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
            popupAnchor: [0, -20],
        });

        const marker = L.marker([item.latitude, item.longitude], {
            icon: customIcon,
        });

        const popupHtml = `
            <div class="flex flex-col gap-2 p-1 text-xs text-slate-200">
                <div class="view-photo-btn h-28 w-44 overflow-hidden rounded-lg bg-slate-950 cursor-pointer group" data-uuid="${item.uuid}">
                    <img src="${thumbUrl}" class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105" />
                </div>
                <div class="font-semibold text-white truncate max-w-[176px]">${item.place_name || item.city || 'Photo location'}</div>
                <button type="button" class="view-photo-btn mt-1 rounded bg-indigo-600 px-2 py-1.5 text-center font-medium text-white hover:bg-indigo-500 cursor-pointer transition shadow" data-uuid="${item.uuid}">
                    View Photo
                </button>
            </div>
        `;

        marker.bindPopup(popupHtml, {
            className: 'dark-popup',
            maxWidth: 220,
        });

        marker.on('dblclick', (e) => {
            L.DomEvent.stopPropagation(e);
            selectedItem.value = item;
            isViewerOpen.value = true;
        });

        markersLayer.addLayer(marker);
    }
}

onMounted(() => {
    if (!mapContainer.value) return;

    const urlParams =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : null;
    const latParam = urlParams?.get('lat');
    const lngParam = urlParams?.get('lng');
    const zoomParam = urlParams?.get('zoom');

    const lat = latParam ? parseFloat(latParam) : null;
    const lng = lngParam ? parseFloat(lngParam) : null;

    const initialCenter: [number, number] =
        lat !== null && !isNaN(lat) && lng !== null && !isNaN(lng)
            ? [lat, lng]
            : [57.1344, 65.5811];
    const initialZoom = zoomParam
        ? parseInt(zoomParam, 10)
        : lat !== null
          ? 16
          : 12;

    map = L.map(mapContainer.value, {
        center: initialCenter,
        zoom: initialZoom,
        zoomControl: false,
    });

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        className: 'dark-tiles',
        maxZoom: 19,
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    // Click handler for popup buttons
    const handleOpenPhoto = (uuid: string | null) => {
        if (!uuid) return;
        const found = markers.value.find((m) => m.uuid === uuid);
        if (found) {
            selectedItem.value = found;
            isViewerOpen.value = true;
        }
    };

    // 1. Direct binding via Leaflet popupopen event
    map.on('popupopen', (e) => {
        const popupNode = e.popup.getElement();
        if (!popupNode) return;
        const btns = popupNode.querySelectorAll('.view-photo-btn');
        btns.forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                ev.stopPropagation();
                handleOpenPhoto(btn.getAttribute('data-uuid'));
            });
        });
    });

    // 2. Fallback capturing listener on window (fires before Leaflet stopPropagation)
    onMapClick = (e: MouseEvent) => {
        const target = (e.target as HTMLElement | null)?.closest<HTMLElement>(
            '.view-photo-btn',
        );
        if (target) {
            e.preventDefault();
            e.stopPropagation();
            handleOpenPhoto(target.getAttribute('data-uuid'));
        }
    };

    window.addEventListener('click', onMapClick, true);

    map.on('moveend', () => {
        loadMarkers();
    });

    loadMarkers();
});

onUnmounted(() => {
    if (onMapClick) {
        window.removeEventListener('click', onMapClick, true);
    }
    map?.remove();
    map = null;
});
</script>

<template>
    <Head title="Photo Map — Lumina" />

    <div
        class="relative h-screen w-screen overflow-hidden bg-slate-950 text-slate-100"
    >
        <header
            class="absolute top-4 left-4 z-1000 flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/90 p-2 shadow-2xl backdrop-blur"
        >
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
                Gallery
            </Link>

            <span class="text-xs font-semibold text-white"
                >Interactive Photo Map</span
            >

            <span
                class="rounded-full bg-indigo-500/20 px-2 py-0.5 text-[11px] font-medium text-indigo-300"
            >
                {{ totalVisible }} photos in view
            </span>
        </header>

        <div ref="mapContainer" class="h-full w-full"></div>

        <MediaViewer
            :item="selectedItem"
            :items="markers"
            :is-open="isViewerOpen"
            :has-next="hasNext"
            :has-prev="hasPrev"
            @next="nextMedia"
            @prev="prevMedia"
            @select="selectedItem = $event"
            @close="
                isViewerOpen = false;
                selectedItem = null;
            "
        />
    </div>
</template>
<style>
.dark-tiles {
    filter: brightness(0.6) invert(1) contrast(3) hue-rotate(200deg)
        saturate(0.3) brightness(0.7);
}

.leaflet-container {
    background-color: #020617 !important;
}

.dark-popup .leaflet-popup-content-wrapper {
    background-color: #0f172a;
    color: #f8fafc;
    border: 1px solid #1e293b;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.5);
}

.dark-popup .leaflet-popup-tip {
    background-color: #0f172a;
}
</style>
