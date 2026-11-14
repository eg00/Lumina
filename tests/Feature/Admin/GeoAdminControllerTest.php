<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MediaFile;
use App\Models\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class GeoAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => uniqid('admin_', true).'@example.com',
            'password' => bcrypt('secret'),
            'moonshine_user_role_id' => 1,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson(route('admin.geo.reverse', ['lat' => 41.71, 'lon' => 44.82]));
        $response->assertUnauthorized();
    }

    public function test_reverse_geocoding_validation(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson(route('admin.geo.reverse', ['lat' => 100, 'lon' => 44.82]));

        $response->assertUnprocessable();
    }

    public function test_reverse_geocoding_success(): void
    {
        $admin = $this->createAdmin();

        Http::fake([
            'https://nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'Old Town, Tbilisi, Georgia',
                'name' => 'Old Town',
                'address' => [
                    'city' => 'Tbilisi',
                    'country' => 'Georgia',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson(route('admin.geo.reverse', ['lat' => 41.6938, 'lon' => 44.8015]));

        $response->assertOk()
            ->assertJson([
                'country' => 'Georgia',
                'city' => 'Tbilisi',
                'place_name' => 'Old Town, Tbilisi, Georgia',
            ]);
    }

    public function test_search_places_success(): void
    {
        $admin = $this->createAdmin();

        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'place_id' => 12345,
                    'lat' => '41.6934591',
                    'lon' => '44.8014495',
                    'display_name' => 'Tbilisi, Georgia',
                    'name' => 'Tbilisi',
                    'address' => [
                        'city' => 'Tbilisi',
                        'country' => 'Georgia',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson(route('admin.geo.search', ['q' => 'Tbilisi']));

        $response->assertOk()
            ->assertJsonStructure([
                'results' => [
                    '*' => ['lat', 'lon', 'display_name', 'country', 'city', 'place_name'],
                ],
            ])
            ->assertJsonPath('results.0.country', 'Georgia');
    }

    public function test_latest_coordinates_returns_last_geotagged_media(): void
    {
        $admin = $this->createAdmin();
        $source = MediaSource::factory()->create();

        MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'latitude' => 48.8566,
            'longitude' => 2.3522,
            'country' => 'France',
            'city' => 'Paris',
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson(route('admin.geo.latest'));

        $response->assertOk()
            ->assertJson([
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'country' => 'France',
                'city' => 'Paris',
            ]);
    }

    public function test_latest_coordinates_returns_default_when_no_geotagged_media(): void
    {
        MediaFile::query()->whereNotNull('latitude')->update(['latitude' => null, 'longitude' => null]);
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson(route('admin.geo.latest'));

        $response->assertOk()
            ->assertJson([
                'latitude' => null,
                'longitude' => null,
            ]);
    }
}
