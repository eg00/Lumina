<?php

declare(strict_types=1);

use App\Jobs\ReverseGeocodeJob;
use App\Models\MediaFile;
use App\Services\Geo\ReverseGeocodeService;
use App\Services\Media\ExifExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('exif extractor extracts gps coordinates using exiftool when exif_read_data fails', function (): void {
    $fixturePath = base_path('tests/fixtures/sample_with_gps.jpg');
    $extractor = app(ExifExtractor::class);
    $exif = $extractor->extract($fixturePath);

    expect($exif->latitude)->not->toBeNull()
        ->and($exif->longitude)->not->toBeNull();
});

test('reverse geocode service queries nominatim and caches response', function (): void {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'country' => 'Russia',
                'city' => 'Tyumen',
            ],
            'name' => 'Ozon',
            'display_name' => 'Tyumen, Russia',
        ], 200),
    ]);

    $service = app(ReverseGeocodeService::class);
    $result = $service->reverse(57.134435, 65.581113);

    $this->assertIsArray($result);
    expect($result['country'])->toBe('Russia')
        ->and($result['city'])->toBe('Tyumen')
        ->and($result['place_name'])->toContain('Tyumen');
});

test('reverse geocode job updates media file with country, city and place name', function (): void {
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            'address' => [
                'country' => 'France',
                'city' => 'Paris',
            ],
            'display_name' => 'Paris, France',
        ], 200),
    ]);

    $mediaFile = MediaFile::factory()->create([
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'country' => null,
        'city' => null,
        'place_name' => null,
    ]);

    $job = new ReverseGeocodeJob($mediaFile);
    $job->handle(app(ReverseGeocodeService::class));

    $mediaFile->refresh();

    expect($mediaFile->country)->toBe('France')
        ->and($mediaFile->city)->toBe('Paris')
        ->and($mediaFile->place_name)->toBe('Paris, France');
});
