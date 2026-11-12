<script setup lang="ts">
import { Heart, Play } from '@lucide/vue';
import { computed } from 'vue';

export interface MediaItem {
    uuid: string;
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
    (e: 'toggleFavorite', item: MediaItem): void;
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
            : 'Other';

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
    <div class="flex flex-col gap-6 sm:gap-8">
        <section
            v-for="group in timelineGroups"
            :key="group.title"
            class="flex flex-col gap-2"
        >
            <!-- Quiet sticky date header -->
            <div
                class="sticky top-0 z-10 flex items-center justify-between bg-background/85 py-2 backdrop-blur-md transition-colors"
            >
                <h2
                    class="text-sm font-semibold tracking-tight text-foreground"
                >
                    {{ group.title }}
                </h2>
                <span
                    class="text-[11px] font-medium text-muted-foreground/80 tabular-nums"
                >
                    {{ group.items.length }}
                    {{ group.items.length === 1 ? 'item' : 'items' }}
                </span>
            </div>

            <!-- Responsive, dense media grid -->
            <div
                class="grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7"
            >
                <div
                    v-for="item in group.items"
                    :key="item.uuid"
                    :style="{
                        backgroundColor: item.dominant_color || 'transparent',
                    }"
                    class="group relative aspect-square cursor-pointer overflow-hidden rounded-sm bg-muted/30 transition-shadow duration-200 select-none hover:ring-1 hover:ring-ring/30 sm:rounded-md"
                    @click="emit('select', item)"
                >
                    <img
                        v-if="item.thumbnail_path"
                        :src="'/storage/' + item.thumbnail_path"
                        :alt="item.file_path"
                        loading="lazy"
                        class="h-full w-full object-cover transition-transform duration-300 ease-out group-hover:scale-[1.04]"
                    />

                    <!-- Soft bottom vignette for indicators -->
                    <div
                        v-if="item.media_type === 'video' || item.is_favorite"
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-8 bg-linear-to-t from-black/50 to-transparent"
                    ></div>

                    <!-- Video badge -->
                    <div
                        v-if="item.media_type === 'video'"
                        class="absolute bottom-1.5 left-1.5 flex items-center justify-center rounded-full bg-black/60 p-1 text-white shadow-sm backdrop-blur-md transition-transform group-hover:scale-105"
                        title="Video"
                    >
                        <Play class="ml-0.5 size-2.5 fill-white stroke-none" />
                    </div>

                    <!-- Favorite indicator -->
                    <div
                        v-if="item.is_favorite"
                        class="absolute top-1.5 right-1.5 text-rose-500 drop-shadow-md transition-transform group-hover:scale-110"
                        title="Favorite"
                    >
                        <Heart class="size-3.5 fill-rose-500 stroke-rose-500" />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
