<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\Geo\ReverseGeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoAdminController extends Controller
{
    public function __construct(
        private readonly ReverseGeocodeService $reverseGeocodeService,
    ) {}

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $geo = $this->reverseGeocodeService->reverse(
            (float) $validated['lat'],
            (float) $validated['lon']
        );

        return response()->json([
            'country' => $geo['country'] ?? null,
            'city' => $geo['city'] ?? null,
            'place_name' => $geo['place_name'] ?? null,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $results = $this->reverseGeocodeService->search((string) $validated['q']);

        return response()->json([
            'results' => $results,
        ]);
    }

    public function latestCoordinates(): JsonResponse
    {
        $latest = MediaFile::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('id')
            ->first(['latitude', 'longitude', 'country', 'city', 'place_name']);

        if ($latest !== null && $latest->latitude !== null && $latest->longitude !== null) {
            return response()->json([
                'latitude' => (float) $latest->latitude,
                'longitude' => (float) $latest->longitude,
                'country' => $latest->country,
                'city' => $latest->city,
                'place_name' => $latest->place_name,
            ]);
        }

        return response()->json([
            'latitude' => null,
            'longitude' => null,
            'country' => null,
            'city' => null,
            'place_name' => null,
        ]);
    }
}
