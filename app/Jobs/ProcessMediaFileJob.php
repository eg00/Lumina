<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MediaProcessedEvent;
use App\Models\MediaFile;
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

        if ($file->media_type->isVideo()) {
            $imageSource = $previewGenerator->extractVideoPoster($filePath);
            $dominantColor = $previewGenerator->extractDominantColor($imageSource);
            $thumbnailPath = $previewGenerator->generateWebp($imageSource, "thumbnails/{$hash}_320.webp", 320);
            $previewPath = $previewGenerator->generateWebp($imageSource, "previews/{$hash}_1280.webp", 1280);
            File::delete($imageSource);
            $exif = null;
        } else {
            $exif = $exifExtractor->extract($filePath);
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
            'captured_at' => $exif->capturedAt ?? $file->captured_at,
        ]);

        MediaProcessedEvent::dispatch(
            mediaFileId: $file->id,
            thumbnailPath: $thumbnailPath,
            previewPath: $previewPath,
            dominantColor: $dominantColor,
            capturedAt: $file->captured_at?->toIso8601String(),
        );
    }
}
