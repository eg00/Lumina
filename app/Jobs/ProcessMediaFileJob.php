<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MediaProcessedEvent;
use App\Models\MediaFile;
use App\Models\Setting;
use App\Services\Media\ExifExtractor;
use App\Services\Media\PreviewGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;

class ProcessMediaFileJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public MediaFile $mediaFile)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PreviewGenerator $previewGenerator, ExifExtractor $exifExtractor): void
    {
        $file = $this->mediaFile;
        $filePath = $file->file_path;
        $hash = $file->file_hash;

        $exif = $exifExtractor->extract($filePath);

        if ($file->media_type->isVideo()) {
            $imageSource = $previewGenerator->extractVideoPoster($filePath);
            $dominantColor = $previewGenerator->extractDominantColor($imageSource);
            $thumbnailPath = $previewGenerator->generateWebp($imageSource, "thumbnails/{$hash}_320.webp", 320);
            $previewPath = $previewGenerator->generateWebp($imageSource, "previews/{$hash}_1280.webp", 1280);
            File::delete($imageSource);
        } else {
            $dominantColor = $previewGenerator->extractDominantColor($filePath);
            $thumbnailPath = $previewGenerator->generateWebp($filePath, "thumbnails/{$hash}_320.webp", 320);
            $previewPath = $previewGenerator->generateWebp($filePath, "previews/{$hash}_1280.webp", 1280);
        }

        $file->update([
            'thumbnail_path' => $thumbnailPath,
            'preview_path' => $previewPath,
            'dominant_color' => $dominantColor,
            'width' => $exif->width ?? $file->width,
            'height' => $exif->height ?? $file->height,
            'latitude' => $exif->latitude ?? $file->latitude,
            'longitude' => $exif->longitude ?? $file->longitude,
            'camera_make' => $exif->cameraMake ?? $file->camera_make,
            'camera_model' => $exif->cameraModel ?? $file->camera_model,
            'lens_model' => $exif->lensModel ?? $file->lens_model,
            'f_number' => $exif->fNumber ?? $file->f_number,
            'exposure_time' => $exif->exposureTime ?? $file->exposure_time,
            'iso' => $exif->iso ?? $file->iso,
            'focal_length' => $exif->focalLength ?? $file->focal_length,
            'captured_at' => $exif->capturedAt ?? $file->captured_at ?? $file->mtime,
        ]);

        MediaProcessedEvent::dispatch(
            mediaFileUuid: $file->uuid,
            thumbnailPath: $thumbnailPath,
            previewPath: $previewPath,
            dominantColor: $dominantColor,
            capturedAt: $file->captured_at?->toIso8601String(),
        );

        if ($file->latitude !== null && $file->longitude !== null) {
            ReverseGeocodeJob::dispatch($file);
        }

        if ($previewPath && Setting::get('features.ai_search', true)) {
            GenerateEmbeddingBatchJob::dispatch([$file->id]);
        }
    }
}
