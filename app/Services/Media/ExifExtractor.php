<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\DTO\ExifData;
use Carbon\CarbonImmutable;

class ExifExtractor
{
    public function extract(string $filePath): ExifData
    {
        if (! file_exists($filePath) || ! is_file($filePath)) {
            return new ExifData;
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            return new ExifData;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $imageType = $imageInfo[2];

        $supportedExifTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];
        if (! in_array($imageType, $supportedExifTypes, true)) {
            return new ExifData(height: $height, width: $width);
        }

        $exif = @exif_read_data($filePath, 'ANY_TAG', true);
        if ($exif === false) {
            return new ExifData(height: $height, width: $width);
        }

        $dateString = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
        if ($dateString) {
            try {
                $capturedAt = CarbonImmutable::createFromFormat('Y:m:d H:i:s', $dateString);
            } catch (\Throwable) {
                $capturedAt = null;
            }

        }

        [$latitude, $longitude] = $this->extractGps($exif);

        return new ExifData(
            capturedAt: $capturedAt ?? null,
            latitude: $latitude,
            longitude: $longitude,
            cameraMake: $exif['IFD0']['Make'] ?? null,
            cameraModel: $exif['IFD0']['Model'] ?? null,
            height: $height,
            width: $width,

        );
    }

    /**
     * @param  array<mixed>  $exif
     * @return float[]|null[]
     */
    private function extractGps(array $exif): array
    {
        $gps = $exif['GPS'] ?? [];

        if (
            ! isset($gps['GPSLatitude'], $gps['GPSLatitudeRef'], $gps['GPSLongitude'], $gps['GPSLongitudeRef']) ||
            ! is_array($gps['GPSLatitude']) ||
            ! is_array($gps['GPSLongitude'])
        ) {
            return [null, null];
        }

        $latitude = $this->parseCoordinate($gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? null);
        $longitude = $this->parseCoordinate($gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? null);

        return [$latitude, $longitude];
    }

    private function evalRational(string $value): float
    {
        $parts = explode('/', $value);
        if (count($parts) === 2 && (float) $parts[1] > 0) {
            return (float) $parts[0] / (float) $parts[1];
        }

        return (float) $parts[0];
    }

    /**
     * @param  string[]  $coord
     */
    private function parseCoordinate(array $coord, ?string $ref = null): ?float
    {
        if (count($coord) < 3) {
            return null;
        }

        $degrees = $this->evalRational((string) $coord[0]);
        $minutes = $this->evalRational((string) $coord[1]);
        $seconds = $this->evalRational((string) $coord[2]);

        $val = $degrees + ($minutes / 60) + ($seconds / 3600);

        return ($ref === 'S' || $ref === 'W') ? -$val : $val;
    }
}
