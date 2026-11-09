<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaEmbedding;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaEmbedding>
 */
class MediaEmbeddingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'media_file_id' => MediaFile::factory(),
            'embedding' => array_fill(0, 512, 0.0),
        ];
    }
}
