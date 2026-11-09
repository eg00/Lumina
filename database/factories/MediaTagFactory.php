<?php

namespace Database\Factories;

use App\Enums\TagType;
use App\Models\MediaTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaTag>
 */
class MediaTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => fake()->randomElement(TagType::cases()),
        ];
    }
}
