<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TagType;
use App\Models\MediaTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MediaTagSeeder extends Seeder
{
    /**
     * Seed a base taxonomy of AI-detected photography tags.
     */
    public function run(): void
    {
        $tags = [
            // Nature & landscapes
            'landscape', 'mountains', 'forest', 'desert', 'beach', 'ocean', 'lake', 'river', 'waterfall', 'meadow',
            'valley', 'canyon', 'cliff', 'island', 'glacier', 'volcano', 'cave', 'sky', 'clouds', 'sunset',
            'sunrise', 'dawn', 'dusk', 'night', 'stars', 'moon', 'aurora', 'rainbow', 'fog', 'mist',
            // Weather
            'sunny', 'cloudy', 'rainy', 'stormy', 'snowy', 'windy', 'foggy', 'overcast', 'clear', 'dramatic',
            // Animals
            'dog', 'cat', 'bird', 'horse', 'cow', 'sheep', 'goat', 'deer', 'rabbit', 'fox',
            'bear', 'lion', 'tiger', 'elephant', 'giraffe', 'zebra', 'monkey', 'gorilla', 'panda', 'koala',
            'kangaroo', 'wolf', 'eagle', 'owl', 'hawk', 'penguin', 'dolphin', 'whale', 'shark', 'fish',
            'snake', 'lizard', 'turtle', 'frog', 'butterfly', 'bee', 'insect', 'spider', 'wildlife', 'pet',
            // Urban & architecture
            'city', 'town', 'village', 'street', 'road', 'bridge', 'building', 'skyscraper', 'house', 'church',
            'castle', 'tower', 'monument', 'statue', 'fountain', 'park', 'plaza', 'market', 'port', 'harbor',
            'architecture', 'modern', 'historic', 'ruins', 'industrial', 'urban', 'rural', 'suburban', 'neon', 'nightlife',
            // People & portrait
            'portrait', 'person', 'man', 'woman', 'child', 'baby', 'elderly', 'family', 'couple', 'group',
            'crowd', 'face', 'smile', 'laugh', 'profile', 'silhouette', 'back', 'hands', 'eyes', 'fashion',
            // Objects & still life
            'car', 'bicycle', 'motorcycle', 'train', 'airplane', 'boat', 'ship', 'truck', 'bus', 'tractor',
            'flower', 'tree', 'plant', 'leaf', 'grass', 'rock', 'stone', 'sand', 'water', 'fire',
            'food', 'drink', 'coffee', 'fruit', 'vegetable', 'book', 'camera', 'phone', 'computer', 'furniture',
            // Activities
            'sports', 'running', 'cycling', 'swimming', 'hiking', 'climbing', 'skiing', 'surfing', 'fishing', 'camping',
            'travel', 'tourism', 'wedding', 'party', 'concert', 'festival', 'parade', 'protest', 'market', 'shopping',
            // Abstract / style
            'abstract', 'minimalist', 'black and white', 'colorful', 'vintage', 'retro', 'aerial', 'macro', 'closeup', 'wide angle',
            'panorama', 'reflection', 'shadow', 'light', 'bokeh', 'blur', 'sharp', 'high contrast', 'soft', 'dramatic',
        ];

        // Trim to unique, cap ~150
        $tags = array_values(array_unique($tags));
        $tags = array_slice($tags, 0, 150);

        foreach ($tags as $name) {
            MediaTag::query()->firstOrCreate(['name' => $name], [
                'slug' => Str::slug($name),
                'type' => TagType::AiDetected,
                'is_public' => false,
            ]);
        }
    }
}
