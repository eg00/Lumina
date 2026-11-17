<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportGooglePhotosBatchJob;
use App\Models\MediaFile;
use App\Services\Google\GooglePhotosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class GooglePhotosImportController extends Controller
{
    public function createSession(GooglePhotosService $service): JsonResponse
    {
        try {
            $session = $service->createSession();

            return response()->json($session);
        } catch (Throwable $throwable) {
            return response()->json([
                'error' => $throwable->getMessage(),
            ], 422);
        }
    }

    public function sessionStatus(string $sessionId, GooglePhotosService $service): JsonResponse
    {
        try {
            $session = $service->getSession($sessionId);

            return response()->json($session);
        } catch (Throwable $throwable) {
            return response()->json([
                'error' => $throwable->getMessage(),
            ], 422);
        }
    }

    public function items(Request $request, GooglePhotosService $service): JsonResponse
    {
        try {
            $sessionId = $request->filled('sessionId') ? (string) $request->query('sessionId') : null;
            $pageToken = $request->filled('pageToken') ? (string) $request->query('pageToken') : null;
            $pageSize = max(1, min(100, (int) $request->query('pageSize', 50)));

            $result = $sessionId !== null && $sessionId !== ''
                ? $service->listPickerMediaItems($sessionId, $pageToken, $pageSize)
                : $service->listLibraryMediaItems($pageToken, $pageSize);
        } catch (Throwable $throwable) {
            return response()->json([
                'error' => $throwable->getMessage(),
                'items' => [],
                'nextPageToken' => null,
            ], 422);
        }

        /** @var list<array<string, mixed>> $mediaItems */
        $mediaItems = $result['mediaItems'];

        $ids = [];
        foreach ($mediaItems as $item) {
            if (isset($item['id']) && is_string($item['id'])) {
                $ids[] = $item['id'];
            }
        }

        $existingIds = MediaFile::query()
            ->whereIn('google_photo_id', $ids)
            ->pluck('google_photo_id')
            ->all();

        $existingSet = array_flip($existingIds);

        foreach ($mediaItems as &$item) {
            $itemId = isset($item['id']) && is_string($item['id']) ? $item['id'] : '';
            $item['is_imported'] = isset($existingSet[$itemId]);
        }

        unset($item);

        return response()->json([
            'items' => $mediaItems,
            'nextPageToken' => $result['nextPageToken'],
        ]);
    }

    public function thumbnail(Request $request, GooglePhotosService $service): Response
    {
        $url = (string) $request->query('url', '');

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return response('Invalid URL', 400);
        }

        if (! preg_match('#^https://lh[0-9]*\.googleusercontent\.com/#i', $url)) {
            return response('Forbidden host', 403);
        }

        $size = max(50, min(1000, (int) $request->query('size', 300)));

        $result = $service->downloadThumbnail($url, $size);

        if ($result['status'] !== 200) {
            return response($result['body'], $result['status']);
        }

        return response($result['body'], 200, [
            'Content-Type' => $result['contentType'],
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function import(Request $request, GooglePhotosService $service): JsonResponse
    {
        $validated = $request->validate([
            'media_source_id' => ['required', 'integer', 'exists:media_sources,id'],
            'session_id' => ['nullable', 'string', 'required_without:items'],
            'items' => ['nullable', 'array', 'required_without:session_id'],
            'items.*.id' => ['required_with:items', 'string'],
            'items.*.baseUrl' => ['required_with:items', 'string'],
        ]);

        $mediaSourceId = (int) $validated['media_source_id'];

        if (! empty($validated['session_id'])) {
            try {
                $items = [];
                $pageToken = null;
                do {
                    $result = $service->listPickerMediaItems((string) $validated['session_id'], $pageToken, 100);
                    /** @var list<array<string, mixed>> $pageItems */
                    $pageItems = $result['mediaItems'];
                    array_push($items, ...$pageItems);
                    $pageToken = $result['nextPageToken'];
                } while ($pageToken !== null && $pageToken !== '');
            } catch (Throwable $e) {
                return response()->json([
                    'error' => 'Failed to fetch items from Google Photos session: '.$e->getMessage(),
                ], 422);
            }
        } else {
            /** @var list<array<string, mixed>> $items */
            $items = $validated['items'] ?? [];
        }

        if (empty($items)) {
            return response()->json([
                'error' => 'No media items found to import.',
            ], 422);
        }

        ImportGooglePhotosBatchJob::dispatch($items, $mediaSourceId);

        return response()->json([
            'success' => true,
            'count' => count($items),
        ]);
    }
}
