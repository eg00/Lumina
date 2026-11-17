<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaType;
use App\Events\MediaImportProgressEvent;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Services\Google\GooglePhotosService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ImportGooglePhotosBatchJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public array $items,
        public int $mediaSourceId
    ) {}

    public function handle(GooglePhotosService $service): void
    {
        $source = MediaSource::query()->find($this->mediaSourceId);

        if ($source === null) {
            return;
        }

        $accessToken = $service->getValidAccessToken();
        $total = count($this->items);
        $imported = 0;

        $tempDir = storage_path('framework/temp/google_photos_import');
        File::ensureDirectoryExists($tempDir);

        foreach ($this->items as $item) {
            $imported++;
            $photoId = isset($item['id']) && is_string($item['id']) ? $item['id'] : '';
            $filename = isset($item['filename']) && is_string($item['filename']) ? $item['filename'] : 'photo.jpg';
            $baseUrl = isset($item['baseUrl']) && is_string($item['baseUrl']) ? $item['baseUrl'] : '';
            $mimeType = isset($item['mimeType']) && is_string($item['mimeType']) ? $item['mimeType'] : 'image/jpeg';

            if ($baseUrl === '' || $photoId === '') {
                continue;
            }

            $downloadUrl = $baseUrl.(str_starts_with($mimeType, 'video/') ? '=dv' : '=d');
            $tempPath = $tempDir.'/'.Str::uuid()->toString().'.tmp';

            try {
                $headers = [];
                if ($accessToken !== null && $accessToken !== '') {
                    $headers['Authorization'] = 'Bearer '.$accessToken;
                }

                $response = Http::withHeaders($headers)
                    ->withOptions(['sink' => $tempPath])
                    ->timeout(120)
                    ->get($downloadUrl);

                if (! $response->successful()) {
                    File::delete($tempPath);
                    $this->dispatchProgress($total, $imported, $filename, 'failed', $source->id);

                    continue;
                }
            } catch (Throwable) {
                File::delete($tempPath);
                $this->dispatchProgress($total, $imported, $filename, 'failed', $source->id);

                continue;
            }

            $hash = hash_file('sha256', $tempPath);

            if ($hash === false) {
                File::delete($tempPath);

                continue;
            }

            $existing = MediaFile::query()->where('file_hash', $hash)->first();

            if ($existing !== null) {
                File::delete($tempPath);

                if ($existing->google_photo_id === null) {
                    $existing->update(['google_photo_id' => $photoId]);
                }

                $this->dispatchProgress($total, $imported, $filename, 'duplicate', $source->id);

                continue;
            }

            /** @var ?string $creationTime */
            $creationTime = $item['creationTime'] ?? $item['mediaMetadata']['creationTime'] ?? null;

            try {
                $capturedAt = $creationTime !== null && $creationTime !== ''
                    ? CarbonImmutable::parse($creationTime)
                    : CarbonImmutable::now();
            } catch (Throwable) {
                $capturedAt = CarbonImmutable::now();
            }

            $year = $capturedAt->format('Y');
            $month = $capturedAt->format('m');
            $targetDir = rtrim($source->path, '/')."/{$year}/{$month}";
            File::ensureDirectoryExists($targetDir);

            $sanitizedName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            if ($sanitizedName === null || $sanitizedName === '') {
                $sanitizedName = 'photo.jpg';
            }

            $targetPath = $targetDir.'/'.$sanitizedName;

            if (File::exists($targetPath)) {
                $nameWithoutExt = pathinfo($sanitizedName, PATHINFO_FILENAME);
                $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
                $targetPath = $targetDir.'/'.$nameWithoutExt.'_'.substr($hash, 0, 8).($extension !== '' ? '.'.$extension : '');
            }

            File::move($tempPath, $targetPath);

            $fileSize = File::size($targetPath);
            $detectedMime = File::mimeType($targetPath);
            $finalMime = is_string($detectedMime) && $detectedMime !== '' ? $detectedMime : $mimeType;
            $mediaType = str_starts_with($finalMime, 'video/') ? MediaType::Video : MediaType::Image;

            $mediaFile = MediaFile::query()->create([
                'media_source_id' => $source->id,
                'google_photo_id' => $photoId,
                'file_path' => $targetPath,
                'file_hash' => $hash,
                'file_size' => $fileSize,
                'mime_type' => $finalMime,
                'media_type' => $mediaType,
                'captured_at' => $capturedAt,
                'mtime' => $capturedAt,
                'is_favorite' => false,
                'is_ignored' => false,
            ]);

            ProcessMediaFileJob::dispatch($mediaFile);

            $this->dispatchProgress($total, $imported, $filename, 'imported', $source->id);
        }
    }

    private function dispatchProgress(int $total, int $imported, string $filename, string $status, int $sourceId): void
    {
        MediaImportProgressEvent::dispatch($total, $imported, $filename, $status, $sourceId, $imported >= $total);
    }
}
