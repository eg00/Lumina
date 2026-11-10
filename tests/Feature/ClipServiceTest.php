<?php

declare(strict_types=1);

use App\Jobs\GenerateEmbeddingBatchJob;
use App\Models\MediaFile;
use App\Services\Ai\ClipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('embed text returns 512 dimension vector', function (): void {
    $mockVector = array_fill(0, 512, 0.05);
    Http::fake([
        'http://ai-worker:8000/embed-text' => Http::response(['embedding' => $mockVector], 200),
    ]);

    $service = app(ClipService::class);
    $vector = $service->embedText('dog on a beach');

    expect($vector)->toHaveCount(512)
        ->and($vector[0])->toBe(0.05);
});

test('embed text gracefully returns empty array when ai-worker is unreachable', function (): void {
    Http::fake([
        'http://ai-worker:8000/embed-text' => Http::response([], 500),
    ]);

    $service = app(ClipService::class);
    $vector = $service->embedText('sunset over mountains');

    expect($vector)->toBeEmpty();
});

test('generate embedding batch job persists vector into media embeddings', function (): void {
    $mockVector = array_fill(0, 512, 0.01);
    Http::fake([
        'http://ai-worker:8000/embed-images' => Http::response([
            'results' => [
                'preview.webp' => $mockVector,
            ],
        ], 200),
    ]);

    /** @var MediaFile $file */
    $file = MediaFile::factory()->create(['preview_path' => 'preview.webp']);

    $job = new GenerateEmbeddingBatchJob([$file->id]);
    $job->handle(app(ClipService::class));

    $this->assertDatabaseHas('media_embeddings', [
        'media_file_id' => $file->id,
    ]);
});
