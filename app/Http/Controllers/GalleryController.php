<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\Setting;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $defaultSort = Setting::get('features.default_sort', 'captured_at_desc');
        $sort = $request->query('sort', $defaultSort);

        $query = MediaFile::active()->with('tags');

        match ($sort) {
            'captured_at_asc' => $query->oldest('captured_at')->orderBy('id'),
            'mtime_desc' => $query->orderByDesc('mtime')->orderByDesc('id'),
            'mtime_asc' => $query->orderBy('mtime')->orderBy('id'),
            default => $query->latest('captured_at')->orderByDesc('id'),
        };

        if ($request->boolean('favorite')) {
            $query->where('is_favorite', true);
        }

        if ($request->query('tag')) {
            $query->whereHas('tags', function (Builder $query) use ($request): void {
                $query->where('slug', $request->query('tag'));
            });
        }

        $media = $query->paginate(60)->withQueryString();
        $features = [
            'ai_search' => Setting::get('features.ai_search'),
            'geo_map' => Setting::get('features.geo_map'),
            'tags' => Setting::get('features.tags'),
            'default_sort' => $sort,
        ];

        return Inertia::render('Gallery/Index', [
            'media' => $media,
            'features' => $features,
            'mercure' => [
                'hubUrl' => (string) (config('broadcasting.connections.mercure.public_url', config('broadcasting.connections.mercure.url'))),
            ],
        ]);
    }

    public function view(MediaFile $mediaFile, Request $request): Response
    {
        if ($mediaFile->is_ignored) {
            abort(404);
        }

        return $this->index($request)
            ->with('activeMediaId', $mediaFile->uuid)
            ->with('activeMediaItem', $mediaFile->load('tags'));
    }
}
