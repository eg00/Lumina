<?php

declare(strict_types=1);

use App\Models\MediaFile;
use App\Models\MediaTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('gallery index only returns public tags and filters by public tag slug', function (): void {
    $file = MediaFile::factory()->create(['is_ignored' => false]);

    $publicTag = MediaTag::factory()->create([
        'name' => 'Visible Sun',
        'slug' => 'visible-sun',
        'is_public' => true,
    ]);

    $hiddenTag = MediaTag::factory()->hidden()->create([
        'name' => 'Hidden Glitch',
        'slug' => 'hidden-glitch',
    ]);

    $file->tags()->attach([$publicTag->id, $hiddenTag->id]);

    $response = test()->get(route('home'));
    $response->assertOk();

    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 1)
        ->where('media.data.0.uuid', $file->uuid)
        ->has('media.data.0.tags', 1)
        ->where('media.data.0.tags.0.id', $publicTag->id)
    );

    // Filtering by hidden tag slug returns empty result
    $hiddenFilterResponse = test()->get(route('home', ['tag' => 'hidden-glitch']));
    $hiddenFilterResponse->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 0)
    );
});
