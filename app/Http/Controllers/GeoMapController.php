<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeoMapController extends Controller
{
    public function index(): Response
    {
        if (! (bool) Setting::get('features.geo_map', true)) {
            abort(404);
        }

        $features = [
            'ai_search' => (bool) Setting::get('features.ai_search', true),
            'geo_map' => (bool) Setting::get('features.geo_map', true),
            'tags' => (bool) Setting::get('features.tags', true),
            'default_sort' => (string) Setting::get('features.default_sort', 'captured_at_desc'),
        ];

        return Inertia::render('Gallery/Map', [
            'features' => $features,
        ]);
    }

    public function markers(Request $request): JsonResponse
    {
        $query = MediaFile::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('bounds')) {
            $parts = explode(',', (string) $request->query('bounds'));
            if (count($parts) === 4) {
                [$minLat, $minLng, $maxLat, $maxLng] = array_map(floatval(...), $parts);
                $query->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
            }
        }

        $markers = $query->select([
            'uuid',
            'latitude',
            'longitude',
            'thumbnail_path',
            'preview_path',
            'file_path',
            'place_name',
            'city',
            'country',
            'captured_at',
            'dominant_color',
            'media_type',
        ])
            ->latest('captured_at')
            ->limit(500)
            ->get();

        return response()->json($markers);
    }
}
