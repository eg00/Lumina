<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaSource>
 */
class MediaSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'path' => fake()->filePath(),
            'is_active' => fake()->boolean(80),
            'scan_interval_hours' => fake()->numberBetween(1, 168),
            'last_scanned_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
