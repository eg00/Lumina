<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\Setting;
use App\Services\Ai\ClipService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSearchController extends Controller
{
    public function similar(MediaFile $mediaFile): Response
    {
        if ($mediaFile->is_ignored) {
            abort(404);
        }

        $mediaFile->loadMissing(['tags', 'embedding']);
        $rawEmbedding = $mediaFile->embedding?->embedding;

        if (is_array($rawEmbedding) && $rawEmbedding !== []) {
            $vectorString = '['.implode(',', $rawEmbedding).']';

            $similarMedia = MediaFile::active()
                ->join('media_embeddings', 'media_files.id', '=', 'media_embeddings.media_file_id')
                ->selectRaw('media_files.*, (media_embeddings.embedding <=> ?) as distance', [$vectorString])
                ->where('media_files.id', '!=', $mediaFile->id)
                ->orderBy('distance')
                ->with('tags')
                ->paginate(40);
        } else {
            $similarMedia = MediaFile::active()
                ->where('id', '!=', $mediaFile->id)
                ->whereRaw('1 = 0')
                ->paginate(40);
        }

        return Inertia::render('Gallery/Similar', [
            'targetMedia' => $mediaFile,
            'media' => $similarMedia,
        ]);
    }

    public function search(Request $request, ClipService $clipService): Response
    {
        $query = trim((string) $request->query('q', ''));
        $vector = $query !== '' ? $clipService->embedText($query) : [];

        if ($vector !== []) {
            $vectorString = '['.implode(',', $vector).']';

            $media = MediaFile::active()
                ->join('media_embeddings', 'media_files.id', '=', 'media_embeddings.media_file_id')
                ->selectRaw('media_files.*, (media_embeddings.embedding <=> ?) as distance', [$vectorString])
                ->orderBy('distance')
                ->with('tags')
                ->paginate(60)
                ->withQueryString();
        } else {
            $media = MediaFile::active()
                ->when($query !== '', function (Builder $builder) use ($query): void {
                    $builder->where(function (Builder $subQuery) use ($query): void {
                        $subQuery->where('file_path', 'ilike', "%{$query}%")
                            ->orWhere('city', 'ilike', "%{$query}%")
                            ->orWhere('country', 'ilike', "%{$query}%")
                            ->orWhere('place_name', 'ilike', "%{$query}%")
                            ->orWhereHas('tags', function (Builder $tagQuery) use ($query): void {
                                $tagQuery->where('name', 'ilike', "%{$query}%");
                            });
                    });
                })
                ->latest('captured_at')
                ->orderByDesc('id')
                ->with('tags')
                ->paginate(60)
                ->withQueryString();
        }

        $features = [
            'ai_search' => (bool) Setting::get('features.ai_search', true),
            'geo_map' => (bool) Setting::get('features.geo_map', true),
            'tags' => (bool) Setting::get('features.tags', true),
            'default_sort' => (string) Setting::get('features.default_sort', 'captured_at_desc'),
        ];

        return Inertia::render('Gallery/Index', [
            'media' => $media,
            'features' => $features,
            'searchQuery' => $query,
            'mercure' => [
                'hubUrl' => (string) (config('broadcasting.connections.mercure.public_url', config('broadcasting.connections.mercure.url'))),
            ],
        ]);
    }
}
