<script setup lang="ts">
import { computed } from 'vue';

export interface MediaItem {
    id: number;
    file_path: string;
    mime_type: string;
    media_type: 'image' | 'video';
    width: number | null;
    height: number | null;
    file_size?: number;
    dominant_color: string | null;
    thumbnail_path: string | null;
    preview_path: string | null;
    captured_at: string | null;
    mtime: string;
    is_favorite: boolean;
    latitude?: number | null;
    longitude?: number | null;
    country?: string | null;
    city?: string | null;
    place_name?: string | null;
    tags?: Array<{ id: number; name: string; slug: string }>;
}

const props = defineProps<{
    items: MediaItem[];
}>();

const emit = defineEmits<{
    (e: 'select', item: MediaItem): void;
}>();

interface TimelineGroup {
    title: string;
    items: MediaItem[];
}

const timelineGroups = computed<TimelineGroup[]>(() => {
    const groups = new Map<string, MediaItem[]>();

    for (const item of props.items) {
        const dateStr = item.captured_at || item.mtime;
        const date = new Date(dateStr);
        const title = !isNaN(date.getTime())
            ? new Intl.DateTimeFormat('en-US', {
                  month: 'long',
                  year: 'numeric',
              }).format(date)
            : 'No date';

        if (!groups.has(title)) {
            groups.set(title, []);
        }
        groups.get(title)!.push(item);
    }

    return Array.from(groups.entries()).map(([title, items]) => ({
        title,
        items,
    }));
});
</script>
<template>
    <div class="flex flex-col gap-8">
        <div
            v-for="group in timelineGroups"
            :key="group.title"
            class="flex flex-col gap-3"
        >
            <h2
                class="sticky top-14 z-10 py-2 text-lg font-semibold text-slate-200 capitalize backdrop-blur"
            >
                {{ group.title }}
            </h2>

            <div
                class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
            >
                <div
                    v-for="item in group.items"
                    :key="item.id"
                    :style="{
                        backgroundColor: item.dominant_color || '#1e293b',
                    }"
                    class="group relative aspect-square cursor-pointer overflow-hidden rounded-lg"
                    @click="emit('select', item)"
                >
                    <img
                        v-if="item.thumbnail_path"
                        :src="'/storage/' + item.thumbnail_path"
                        :alt="item.file_path"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />

                    <!-- Video badge -->
                    <div
                        v-if="item.media_type === 'video'"
                        class="absolute bottom-2 left-2 rounded-full bg-black/60 p-1 text-white shadow backdrop-blur"
                    >
                        <svg
                            class="h-3.5 w-3.5 fill-current"
                            viewBox="0 0 24 24"
                        >
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                    </div>

                    <div
                        v-if="item.is_favorite"
                        class="absolute top-2 right-2 text-rose-500 drop-shadow"
                    >
                        <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24">
                            <path
                                d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
