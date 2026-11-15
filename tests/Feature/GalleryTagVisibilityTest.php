<?php

declare(strict_types=1);

use App\Models\MediaFile;
use App\Models\MediaTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $mediaItems = $response->viewData('page')['props']['media']['data'];
    $fileItem = collect($mediaItems)->firstWhere('uuid', $file->uuid);

    expect($fileItem['tags'])->toHaveCount(1)
        ->and($fileItem['tags'][0]['id'])->toBe($publicTag->id);

    // Filtering by hidden tag slug returns empty result
    $hiddenFilterResponse = test()->get(route('home', ['tag' => 'hidden-glitch']));
    $hiddenItems = $hiddenFilterResponse->viewData('page')['props']['media']['data'];
    expect($hiddenItems)->toBeEmpty();
});
