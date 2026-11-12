<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Services\Geo\ReverseGeocodeService;
use App\Services\Media\ExifExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:reprocess-geo {--force : Reprocess even if coordinates already exist}')]
#[Description('Extract EXIF GPS coordinates via ExifTool and perform reverse geocoding on media files')]
class ReprocessGeoCommand extends Command
{
    public function handle(ExifExtractor $extractor, ReverseGeocodeService $geoService): int
    {
        $query = MediaFile::query()->whereNotNull('file_path');

        if (! $this->option('force')) {
            $query->whereNull('latitude');
        }

        $files = $query->get();
        $total = $files->count();

        if ($total === 0) {
            $this->info('No media files require GPS reprocessing.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Reprocessing GPS and geocoding for %d media files...', $total));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updatedGps = 0;
        $updatedGeo = 0;

        foreach ($files as $file) {
            $exif = $extractor->extract($file->file_path);

            if ($exif->latitude !== null && $exif->longitude !== null) {
                $file->update([
                    'latitude' => $exif->latitude,
                    'longitude' => $exif->longitude,
                    'width' => $exif->width ?? $file->width,
                    'height' => $exif->height ?? $file->height,
                    'captured_at' => $exif->capturedAt ?? $file->captured_at,
                ]);
                $updatedGps++;

                $geoData = $geoService->reverse($exif->latitude, $exif->longitude);
                if ($geoData !== null) {
                    $file->update([
                        'country' => $geoData['country'] ?? $file->country,
                        'city' => $geoData['city'] ?? $file->city,
                        'place_name' => $geoData['place_name'] ?? $file->place_name,
                    ]);
                    $updatedGeo++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf('Complete: %d files got GPS coordinates, %d files geocoded with country/city/place.', $updatedGps, $updatedGeo));

        return self::SUCCESS;
    }
}
