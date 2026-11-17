<?php

declare(strict_types=1);

use App\Enums\TagType;
use App\Jobs\AutoTagMediaFileJob;
use App\Models\MediaFile;
use App\Models\MediaTag;
use App\Services\Ai\ClipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('auto tag job classifies preview image and attaches tags preserving is_public status', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('previews/sample.webp', 'fake_image_bytes');

    $file = MediaFile::factory()->create([
        'preview_path' => 'previews/sample.webp',
    ]);

    // Pre-existing user tag with same name
    $existingUserTag = MediaTag::factory()->create([
        'name' => 'portrait',
        'type' => TagType::User,
        'is_public' => true,
    ]);

    $mockClip = Mockery::mock(ClipService::class);
    $mockClip->shouldReceive('classifyTags')
        ->once()
        ->andReturn([
            ['tag' => 'portrait', 'score' => 0.35],
            ['tag' => 'sunset', 'score' => 0.28],
        ]);

    app()->instance(ClipService::class, $mockClip);

    $job = new AutoTagMediaFileJob($file);
    app()->call($job->handle(...));

    $file->refresh();
    $attachedTags = $file->tags;
    expect($attachedTags)->toHaveCount(2);

    $portrait = $attachedTags->firstWhere('name', 'portrait');
    $sunset = $attachedTags->firstWhere('name', 'sunset');

    test()->assertNotNull($portrait);
    test()->assertNotNull($sunset);

    // Existing user tag retained its public status
    expect($portrait->is_public)->toBeTrue()
        ->and($portrait->type)->toBe(TagType::User)
        // Newly created AI tag is private
        ->and($sunset->is_public)->toBeFalse()
        ->and($sunset->type)->toBe(TagType::AiDetected);
});
