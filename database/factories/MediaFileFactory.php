<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\MediaSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime']);
        $mediaType = str_starts_with($mimeType, 'video/') ? MediaType::Video : MediaType::Image;

        return [
            'media_source_id' => MediaSource::factory(),
            'file_path' => fake()->filePath(),
            'storage_disk' => fake()->randomElement(['local', 's3', 'gcs']),
            'file_hash' => fake()->sha256(),
            'file_size' => fake()->numberBetween(1024, 50_000_000),
            'mime_type' => $mimeType,
            'media_type' => $mediaType,
            'width' => fake()->numberBetween(640, 7680),
            'height' => fake()->numberBetween(480, 4320),
            'duration' => fake()->optional(0.3)->randomFloat(2, 1, 3600),
            'captured_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'mtime' => fake()->dateTimeBetween('-1 month', 'now'),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'country' => fake()->optional()->country(),
            'city' => fake()->optional()->city(),
            'place_name' => fake()->optional()->citySuffix(),
            'dominant_color' => fake()->optional()->hexColor(),
            'is_favorite' => fake()->boolean(10),
            'is_ignored' => fake()->boolean(5),
            'preview_path' => fake()->optional()->filePath(),
            'thumbnail_path' => fake()->optional()->filePath(),
        ];
    }
}
