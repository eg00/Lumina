<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Jobs\GenerateEmbeddingBatchJob;
use App\Models\MediaFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ImageEditorService
{
    public function __construct(
        private readonly PreviewGenerator $previewGenerator,
    ) {}

    /**
     * @param  array{x: int|float, y: int|float, width: int|float, height: int|float}  $crop
     */
    public function apply(
        MediaFile $mediaFile,
        int $rotation,
        array $crop,
        bool $portraitMode,
        int $blurRadius = 20,
    ): MediaFile {
        ini_set('memory_limit', '512M');

        $filePath = $mediaFile->file_path;
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("Media file not found or not readable: {$filePath}");
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mediaFile->mime_type, $allowedMimes, true)) {
            throw new InvalidArgumentException("Unsupported media type for editing: {$mediaFile->mime_type}");
        }

        // 1. Load image into GD
        $image = match ($mediaFile->mime_type) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/webp' => @imagecreatefromwebp($filePath),
        };

        if ($image === false) {
            throw new RuntimeException("Failed to decode image from {$filePath}");
        }

        // 1b. Auto-orient based on EXIF orientation (browsers rotate the preview,
        //     GD does not). Without this the saved file loses its orientation tag
        //     and appears rotated.
        $image = $this->previewGenerator->autoOrient($image, $filePath);

        // 2. Rotate first: frontend crop coordinates are expressed in the (rotated)
        //    image space produced by Cropper, so the source image must be rotated
        //    before cropping. (imagerotate rotates counter-clockwise for positive
        //    values; photo rotation is clockwise, hence the negation.)
        if ($rotation !== 0) {
            $rotated = imagerotate($image, -$rotation, 0);
            if ($rotated !== false) {
                $image = $rotated;
            }
        }

        // 3. Crop using the (now rotated) image dimensions
        $origW = imagesx($image);
        $origH = imagesy($image);

        $cropX = min(max(0, (int) round($crop['x'])), max(0, $origW - 1));
        $cropY = min(max(0, (int) round($crop['y'])), max(0, $origH - 1));
        $cropWidth = min(max(1, (int) round($crop['width'])), $origW - $cropX);
        $cropHeight = min(max(1, (int) round($crop['height'])), $origH - $cropY);

        $cropped = imagecrop($image, [
            'x' => $cropX,
            'y' => $cropY,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);
        if ($cropped !== false) {
            $image = $cropped;
        }

        // 4. Save to temporary file
        $tempPath = $filePath.'.tmp.'.uniqid('', true);
        $saved = match ($mediaFile->mime_type) {
            'image/jpeg' => imagejpeg($image, $tempPath, 95),
            'image/png' => imagepng($image, $tempPath, 9),
            'image/webp' => imagewebp($image, $tempPath, 95),
        };

        if (! $saved || ! file_exists($tempPath)) {
            throw new RuntimeException('Failed to write edited image to temp file');
        }

        // 5. Apply AI portrait blur if enabled
        if ($portraitMode) {
            $aiWorkerUrl = config('services.ai_worker.url', 'http://ai-worker:8000');
            $response = Http::timeout(120)
                ->attach('file', (string) file_get_contents($tempPath), basename($filePath))
                ->post("{$aiWorkerUrl}/portrait-blur", [
                    'blur_radius' => $blurRadius,
                ]);

            if ($response->successful()) {
                file_put_contents($tempPath, $response->body());
            } else {
                File::delete($tempPath);
                throw new RuntimeException('AI Portrait Blur failed: '.$response->body());
            }
        }

        // 6. Atomically replace original file
        if (! @rename($tempPath, $filePath)) {
            if (! @copy($tempPath, $filePath)) {
                File::delete($tempPath);
                throw new RuntimeException("Failed to overwrite original file: {$filePath}");
            }

            File::delete($tempPath);
        }

        // 7. Update dimensions, hash and size
        $imageInfo = getimagesize($filePath);
        $newWidth = $imageInfo !== false ? $imageInfo[0] : 0;
        $newHeight = $imageInfo !== false ? $imageInfo[1] : 0;
        $newSize = filesize($filePath) ?: 0;
        $newHash = hash_file('sha256', $filePath) ?: '';

        // 8. Synchronously regenerate previews and dominant color
        $dominantColor = $this->previewGenerator->extractDominantColor($filePath);
        $thumbnailPath = $this->previewGenerator->generateWebp($filePath, "thumbnails/{$newHash}_320.webp", 320);
        $previewPath = $this->previewGenerator->generateWebp($filePath, "previews/{$newHash}_1280.webp", 1280);

        $mediaFile->update([
            'width' => $newWidth,
            'height' => $newHeight,
            'file_size' => $newSize,
            'file_hash' => $newHash,
            'dominant_color' => $dominantColor,
            'thumbnail_path' => $thumbnailPath,
            'preview_path' => $previewPath,
        ]);

        // 9. Dispatch async CLIP reindex
        GenerateEmbeddingBatchJob::dispatch([$mediaFile->id]);

        $refreshed = $mediaFile->fresh();
        if (! $refreshed) {
            throw new RuntimeException("Failed to reload media file: {$mediaFile->id}");
        }

        return $refreshed;
    }
}
