<?php

declare(strict_types=1);

namespace App\Services\Scanner;

use App\DTO\ScanResult;
use App\Enums\MediaType;
use App\Events\MediaScanProgressEvent;
use App\Jobs\ProcessMediaFileJob;
use App\Models\MediaFile;
use App\Models\MediaSource;
use Carbon\CarbonImmutable;
use File;
use Symfony\Component\Finder\Finder;

class DirectoryScanner
{
    public function scan(MediaSource $source): ScanResult
    {
        $scanned = 0;
        $skippedCount = 0;
        $reusedCount = 0;
        $newCount = 0;

        $finder = app(Finder::class);
        $finder->files()
            ->in($source->path)
            ->exclude(['thumbnails', 'previews', 'cache', '.cache', 'vendor'])
            ->name('/\.(jpg|jpeg|png|webp|heic|avif|gif|tiff|mp4|mov|avi|mkv|webm)$/i');

        $total = $finder->count();

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $normalizedPath = str_replace('\\', '/', $filePath);

            if (str_contains($normalizedPath, '/thumbnails/') || str_contains($normalizedPath, '/previews/')) {
                continue;
            }

            $mtime = CarbonImmutable::createFromTimestamp($file->getMTime());
            $scanned++;

            $existing = MediaFile::query()->where('file_path', $filePath)->first();

            if ($existing && ($existing->is_ignored === true || $existing->mtime->timestamp === $mtime->timestamp)) {
                $skippedCount++;

                continue;
            }

            $hash = hash_file('sha256', $filePath);
            $mimeType = File::mimeType($filePath);
            if ($mimeType === false) {
                $mediaType = MediaType::Image;
            } else {
                $mediaType = MediaType::tryFromMimeType($mimeType) ?? MediaType::Image;
            }

            $duplicate = MediaFile::query()->where('file_hash', $hash)
                ->with('embedding')
                ->whereNotNull('preview_path')
                ->first();
            if ($duplicate) {
                $mediaFile = MediaFile::query()->create([
                    'preview_path' => $duplicate->preview_path,
                    'thumbnail_path' => $duplicate->thumbnail_path,
                    'dominant_color' => $duplicate->dominant_color,
                    'width' => $duplicate->width,
                    'height' => $duplicate->height,
                    'duration' => $duplicate->duration,
                    'captured_at' => $duplicate->captured_at,
                    'latitude' => $duplicate->latitude,
                    'longitude' => $duplicate->longitude,
                    'country' => $duplicate->country,
                    'city' => $duplicate->city,
                    'place_name' => $duplicate->place_name,
                    'mime_type' => $mimeType,
                    'file_hash' => $hash,
                    'mtime' => $mtime,
                    'file_path' => $filePath,
                    'media_source_id' => $source->id,
                    'file_size' => $file->getSize(),
                    'media_type' => $mediaType,
                ]);

                if ($duplicate->embedding) {
                    $mediaFile->embedding()->create([
                        'embedding' => $duplicate->embedding->getRawOriginal('embedding') ?? $duplicate->embedding->embedding,
                    ]);
                }

                $reusedCount++;
                $newCount++;

                continue;
            }

            $mediaFile = MediaFile::query()->create([
                'media_source_id' => $source->id,
                'file_path' => $filePath,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
                'mime_type' => $mimeType,
                'media_type' => $mediaType,
                'mtime' => $mtime,
                'is_favorite' => false,
                'is_ignored' => false,
            ]);

            ProcessMediaFileJob::dispatch($mediaFile);
            $newCount++;

            if ($scanned % 50 === 0) {
                MediaScanProgressEvent::dispatch($source->id, $scanned, $total, false);
            }
        }

        MediaScanProgressEvent::dispatch($source->id, $scanned, $total, true);

        $source->update(['last_scanned_at' => now()]);

        return new ScanResult(
            totalScanned: $total,
            newCount: $newCount,
            reusedCount: $reusedCount,
            skippedCount: $skippedCount,
        );

    }
}
