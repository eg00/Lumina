<?php

declare(strict_types=1);

use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('media file automatically generates a valid uuidv7 on creation', function (): void {
    $mediaFile = MediaFile::factory()->create();

    expect($mediaFile->uuid)->not->toBeNull()
        ->and(Str::isUuid($mediaFile->uuid))->toBeTrue();
});

test('public gallery feed exposes uuidv7 instead of sequential integer id', function (): void {
    $mediaFile = MediaFile::factory()->create([
        'is_ignored' => false,
    ]);

    $response = test()->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->where('media.data.0.uuid', $mediaFile->uuid)
        ->missing('media.data.0.id')
    );
});

test('guest can access media stream and view page using uuidv7', function (): void {
    $mediaFile = MediaFile::factory()->create([
        'is_ignored' => false,
        'storage_disk' => 'local_scan',
        'file_path' => 'test.jpg',
    ]);

    $viewResponse = test()->get("/view/{$mediaFile->uuid}");
    $viewResponse->assertStatus(200);

    // Доступ по целочисленному id должен возвращать 404
    $idResponse = test()->get("/view/{$mediaFile->id}");
    $idResponse->assertStatus(404);
});
