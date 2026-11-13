<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Services\Media\ImageEditorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaEditorController extends Controller
{
    public function __construct(
        private readonly ImageEditorService $imageEditorService,
    ) {}

    public function applyEdit(Request $request, MediaFile $mediaFile): JsonResponse
    {
        Log::info('applyEdit payload for media '.$mediaFile->id, $request->all());

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mediaFile->mime_type, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'mediaFile' => ["Unsupported file format: {$mediaFile->mime_type}. Only JPEG, PNG, and WebP can be edited."],
            ]);
        }

        $validated = $request->validate([
            'rotation' => ['required', 'integer', 'in:-270,-180,-90,0,90,180,270'],
            'crop' => ['required', 'array'],
            'crop.x' => ['required', 'numeric', 'min:0'],
            'crop.y' => ['required', 'numeric', 'min:0'],
            'crop.width' => ['required', 'numeric', 'min:1'],
            'crop.height' => ['required', 'numeric', 'min:1'],
            'portrait_mode' => ['required', 'boolean'],
            'blur_radius' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        /** @var array{x: int|float, y: int|float, width: int|float, height: int|float} $crop */
        $crop = $validated['crop'];
        $rotation = (int) $validated['rotation'];
        $portraitMode = (bool) $validated['portrait_mode'];
        $blurRadius = isset($validated['blur_radius']) ? (int) $validated['blur_radius'] : 20;

        $updated = $this->imageEditorService->apply(
            mediaFile: $mediaFile,
            rotation: $rotation,
            crop: $crop,
            portraitMode: $portraitMode,
            blurRadius: $blurRadius,
        );

        $timestamp = time();
        $thumbnailUrl = $updated->thumbnail_path ? Storage::disk('public')->url($updated->thumbnail_path)."?v={$timestamp}" : null;
        $previewUrl = $updated->preview_path ? Storage::disk('public')->url($updated->preview_path)."?v={$timestamp}" : null;

        return response()->json([
            'success' => true,
            'thumbnail_url' => $thumbnailUrl,
            'preview_url' => $previewUrl,
        ]);
    }

    public function previewBokeh(Request $request, MediaFile $mediaFile): JsonResponse
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mediaFile->mime_type, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'mediaFile' => ["Unsupported file format: {$mediaFile->mime_type}."],
            ]);
        }

        $validated = $request->validate([
            'blur_radius' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $blurRadius = isset($validated['blur_radius']) ? (int) $validated['blur_radius'] : 20;

        $sourcePath = $mediaFile->file_path;

        if (! file_exists($sourcePath) || ! is_readable($sourcePath)) {
            throw ValidationException::withMessages([
                'mediaFile' => ['Source file not accessible for bokeh preview.'],
            ]);
        }

        $aiWorkerUrl = config('services.ai_worker.url', 'http://ai-worker:8000');
        $response = Http::timeout(60)
            ->attach('file', (string) file_get_contents($sourcePath), basename($sourcePath))
            ->post("{$aiWorkerUrl}/portrait-blur", [
                'blur_radius' => $blurRadius,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Failed to generate bokeh preview: '.$response->body(),
            ], 422);
        }

        $base64 = base64_encode($response->body());
        $mime = $response->header('Content-Type') ?: 'image/jpeg';

        return response()->json([
            'preview_data_url' => "data:{$mime};base64,{$base64}",
        ]);
    }
}
