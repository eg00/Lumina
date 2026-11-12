<?php

declare(strict_types=1);

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverseGeocodeService
{
    /**
     * @return array{country: ?string, city: ?string, place_name: ?string}|null
     */
    public function reverse(float $latitude, float $longitude): ?array
    {
        $roundedLat = round($latitude, 4);
        $roundedLon = round($longitude, 4);
        $cacheKey = sprintf('geo:reverse:%s:%s', $roundedLat, $roundedLon);

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($latitude, $longitude): ?array {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'LuminaGallery/1.0',
                ])
                    ->timeout(5)
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'format' => 'jsonv2',
                        'lat' => $latitude,
                        'lon' => $longitude,
                        'accept-language' => 'en',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();
                if (! is_array($data) || empty($data['address'])) {
                    return null;
                }

                /** @var array<string, string> $address */
                $address = $data['address'];

                $country = $address['country'] ?? null;
                $city = $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['municipality']
                    ?? $address['county']
                    ?? null;

                $placeName = $data['display_name'] ?? $data['name'] ?? null;
                if ($placeName === null && ($city !== null || $country !== null)) {
                    $placeName = implode(', ', array_filter([$city, $country]));
                }

                return [
                    'country' => $country,
                    'city' => $city,
                    'place_name' => $placeName,
                ];
            } catch (Throwable $throwable) {
                Log::warning(sprintf('Reverse geocoding failed for [%s, %s]: %s', $latitude, $longitude, $throwable->getMessage()));

                return null;
            }
        });
    }
}
