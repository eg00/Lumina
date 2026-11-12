<?php

declare(strict_types=1);

use App\Models\MediaEmbedding;
use App\Models\MediaFile;
use App\Services\Ai\ClipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('find similar images orders by vector distance', function (): void {
    $target = MediaFile::factory()->create(['is_ignored' => false]);
    $similar = MediaFile::factory()->create(['is_ignored' => false]);
    $different = MediaFile::factory()->create(['is_ignored' => false]);

    $vTarget = array_fill(0, 512, 0.0);
    $vTarget[0] = 1.0;

    $vSimilar = array_fill(0, 512, 0.0);
    $vSimilar[0] = 0.95;
    $vSimilar[1] = 0.05;

    $vDiff = array_fill(0, 512, 0.0);
    $vDiff[511] = 1.0;

    MediaEmbedding::query()->create(['media_file_id' => $target->id, 'embedding' => $vTarget]);
    MediaEmbedding::query()->create(['media_file_id' => $similar->id, 'embedding' => $vSimilar]);
    MediaEmbedding::query()->create(['media_file_id' => $different->id, 'embedding' => $vDiff]);

    $response = test()->get("/media/{$target->uuid}/similar");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Similar')
        ->has('targetMedia')
        ->has('media.data', 2)
        ->where('media.data.0.uuid', $similar->uuid)
        ->where('media.data.1.uuid', $different->uuid)
    );
});

test('semantic text search falls back gracefully when ai service is down', function (): void {
    $mock = mock(ClipService::class);
    $mock->allows('embedText')->andReturns([]);
    app()->instance(ClipService::class, $mock);

    MediaFile::factory()->create(['file_path' => '/media/beach_dog.jpg', 'is_ignored' => false]);
    MediaFile::factory()->create(['file_path' => '/media/mountain_snow.jpg', 'is_ignored' => false]);

    $response = test()->get('/search?q=beach');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 1)
        ->where('media.data.0.file_path', '/media/beach_dog.jpg')
    );
});
