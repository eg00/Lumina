<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Events\MediaProcessedEvent;
use App\Jobs\ProcessMediaFileJob;
use App\Models\MediaFile;
use App\Services\Media\ExifExtractor;
use App\Services\Media\PreviewGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('processing job generates webp thumbnails, extracts dominant color and broadcasts completion for image', function (): void {
    Storage::fake('public');
    Event::fake([MediaProcessedEvent::class]);

    $fixturePath = base_path('tests/fixtures/sample_with_gps.jpg');

    /** @var MediaFile $mediaFile */
    $mediaFile = MediaFile::factory()->create([
        'file_path' => $fixturePath,
        'media_type' => MediaType::Image,
        'mime_type' => 'image/jpeg',
        'file_hash' => hash_file('sha256', $fixturePath),
    ]);

    $job = new ProcessMediaFileJob($mediaFile);
    $job->handle(app(PreviewGenerator::class), app(ExifExtractor::class));

    $mediaFile->refresh();

    expect($mediaFile->thumbnail_path)->not->toBeNull()
        ->and($mediaFile->preview_path)->not->toBeNull()
        ->and($mediaFile->dominant_color)->toMatch('/^#[0-9a-fA-F]{6}$/')
        ->and($mediaFile->latitude)->not->toBeNull()
        ->and($mediaFile->longitude)->not->toBeNull()
        ->and($mediaFile->captured_at)->not->toBeNull();

    Storage::disk('public')->assertExists((string) $mediaFile->thumbnail_path);
    Storage::disk('public')->assertExists((string) $mediaFile->preview_path);

    Event::assertDispatched(MediaProcessedEvent::class, fn (MediaProcessedEvent $event): bool => $event->mediaFileUuid === $mediaFile->uuid);
});

test('processing job extracts poster and generates webp thumbnails for video', function (): void {
    Storage::fake('public');
    Event::fake([MediaProcessedEvent::class]);

    $fixturePath = base_path('tests/fixtures/sample_video.mp4');

    /** @var MediaFile $mediaFile */
    $mediaFile = MediaFile::factory()->create([
        'file_path' => $fixturePath,
        'media_type' => MediaType::Video,
        'mime_type' => 'video/mp4',
        'file_hash' => hash_file('sha256', $fixturePath),
    ]);

    $job = new ProcessMediaFileJob($mediaFile);
    $job->handle(app(PreviewGenerator::class), app(ExifExtractor::class));

    $mediaFile->refresh();

    expect($mediaFile->thumbnail_path)->not->toBeNull()
        ->and($mediaFile->preview_path)->not->toBeNull()
        ->and($mediaFile->dominant_color)->toMatch('/^#[0-9a-fA-F]{6}$/')
        ->and($mediaFile->width)->toBe(320)
        ->and($mediaFile->height)->toBe(240);

    Storage::disk('public')->assertExists((string) $mediaFile->thumbnail_path);
    Storage::disk('public')->assertExists((string) $mediaFile->preview_path);

    Event::assertDispatched(MediaProcessedEvent::class);
});
