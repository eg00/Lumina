<?php

declare(strict_types=1);

use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('markers endpoint filters by bounding box', function (): void {
    $paris = MediaFile::factory()->create([
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'is_ignored' => false,
    ]);

    $tokyo = MediaFile::factory()->create([
        'latitude' => 35.6762,
        'longitude' => 139.6503,
        'is_ignored' => false,
    ]);

    $response = test()->getJson('/api/geo/markers?bounds=40.0,-10.0,55.0,20.0');

    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $paris->id])
        ->assertJsonMissing(['id' => $tokyo->id]);
});

test('map page renders inertia view with features', function (): void {
    MediaFile::factory()->create([
        'latitude' => 57.1344,
        'longitude' => 65.5811,
        'is_ignored' => false,
    ]);

    $response = test()->get('/map');

    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Gallery/Map')
        ->has('features')
    );
});
