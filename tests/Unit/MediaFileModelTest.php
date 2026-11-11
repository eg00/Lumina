<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Models\MediaEmbedding;
use App\Models\MediaFile;
use App\Models\MediaTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create media file with dominant color and query by cosine distance', function (): void {
    /** @var MediaFile $fileA */
    $fileA = MediaFile::factory()->create(['file_path' => '/media/a.jpg', 'dominant_color' => '#3b82f6']);
    /** @var MediaFile $fileB */
    $fileB = MediaFile::factory()->create(['file_path' => '/media/b.jpg', 'dominant_color' => '#ef4444']);

    $vectorA = array_fill(0, 512, 0.0);
    $vectorA[0] = 1.0;
    $vectorB = array_fill(0, 512, 0.0);
    $vectorB[1] = 1.0;

    MediaEmbedding::query()->create(['media_file_id' => $fileA->id, 'embedding' => json_encode($vectorA)]);
    MediaEmbedding::query()->create(['media_file_id' => $fileB->id, 'embedding' => json_encode($vectorB)]);

    $results = MediaFile::query()
        ->join('media_embeddings', 'media_files.id', '=', 'media_embeddings.media_file_id')
        ->selectRaw('media_files.*, (media_embeddings.embedding <=> ?) as distance', [json_encode($vectorA)])
        ->orderBy('distance')
        ->get();

    $first = $results->first();
    if ($first === null) {
        throw new UnexpectedValueException('Expected at least one result');
    }

    expect($first->id)->toBe($fileA->id)
        ->and($first->dominant_color)->toBe('#3b82f6')
        ->and((float) $first->distance)->toBe(0.0);
});

test('ignored and soft deleted scope filters correctly', function (): void {
    $active = MediaFile::factory()->create(['is_ignored' => false]);
    $ignored = MediaFile::factory()->create(['is_ignored' => true]);

    expect(MediaFile::active()->get()->pluck('id'))->toContain($active->id)
        ->not->toContain($ignored->id);
});

test('casts media_type to MediaType enum and synchronizes relations', function (): void {
    /** @var MediaFile $mediaFile */
    $mediaFile = MediaFile::factory()->create([
        'mime_type' => 'image/jpeg',
        'media_type' => MediaType::Image,
    ]);

    expect($mediaFile->media_type)->toBe(MediaType::Image)
        ->and($mediaFile->media_type->isImage())->toBeTrue()
        ->and($mediaFile->media_type->isVideo())->toBeFalse()
        ->and($mediaFile->mediaSource)->not->toBeNull();

    $tag = MediaTag::factory()->create();
    $mediaFile->tags()->attach($tag);

    expect($mediaFile->tags)->toHaveCount(1)
        ->and($mediaFile->tags->first()?->id)->toBe($tag->id);
});
