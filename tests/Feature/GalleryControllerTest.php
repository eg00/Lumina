<?php

declare(strict_types=1);

use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guest can access gallery feed without authentication with dominant color props', function (): void {
    MediaFile::factory()->count(5)->create([
        'is_ignored' => false,
        'dominant_color' => '#64748b',
    ]);

    $response = test()->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 5)
        ->where('media.data.0.dominant_color', '#64748b')
        ->has('features')
        ->has('mercure')
    );
});

test('gallery ignores files marked as ignored', function (): void {
    MediaFile::factory()->create(['is_ignored' => false]);
    MediaFile::factory()->create(['is_ignored' => true]);

    $response = test()->get('/');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 1)
    );
});

test('gallery can filter by favorite and tag', function (): void {
    $fav = MediaFile::factory()->create(['is_favorite' => true, 'is_ignored' => false]);
    $regular = MediaFile::factory()->create(['is_favorite' => false, 'is_ignored' => false]);

    $favResponse = test()->get('/?favorite=1');
    $favResponse->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->has('media.data', 1)
        ->where('media.data.0.uuid', $fav->uuid)
    );
});

test('gallery supports sorting by captured_at_asc', function (): void {
    $older = MediaFile::factory()->create([
        'captured_at' => now()->subDays(5),
        'is_ignored' => false,
    ]);
    $newer = MediaFile::factory()->create([
        'captured_at' => now()->subDay(),
        'is_ignored' => false,
    ]);

    $response = test()->get('/?sort=captured_at_asc');
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->where('media.data.0.uuid', $older->uuid)
    );
});

test('direct photo view route loads gallery with active media props', function (): void {
    $file = MediaFile::factory()->create(['is_ignored' => false]);

    $response = test()->get("/view/{$file->uuid}");

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('Gallery/Index')
        ->where('activeMediaId', $file->uuid)
        ->has('activeMediaItem')
    );
});

test('direct photo view route returns 404 for ignored media', function (): void {
    $file = MediaFile::factory()->create(['is_ignored' => true]);

    $response = test()->get("/view/{$file->uuid}");

    $response->assertStatus(404);
});
