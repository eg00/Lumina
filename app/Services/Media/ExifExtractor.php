<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\DTO\ExifData;
use Carbon\CarbonImmutable;
use Symfony\Component\Process\Process;
use Throwable;

class ExifExtractor
{
    public function extract(string $filePath): ExifData
    {
        if (! file_exists($filePath) || ! is_file($filePath)) {
            return new ExifData;
        }

        // 1. Primary: Extract metadata via ExifTool (supports images and videos)
        $exifToolData = $this->extractViaExifTool($filePath);
        if ($exifToolData instanceof ExifData) {
            // Fallback to getimagesize if dimensions were missing from ExifTool
            if ($exifToolData->width === null || $exifToolData->height === null) {
                $imageInfo = @getimagesize($filePath);
                if ($imageInfo !== false) {
                    return new ExifData(
                        capturedAt: $exifToolData->capturedAt,
                        latitude: $exifToolData->latitude,
                        longitude: $exifToolData->longitude,
                        cameraMake: $exifToolData->cameraMake,
                        cameraModel: $exifToolData->cameraModel,
                        height: $exifToolData->height ?? $imageInfo[1],
                        width: $exifToolData->width ?? $imageInfo[0],
                    );
                }
            }

            return $exifToolData;
        }

        // 2. Secondary fallback: PHP native exif_read_data and getimagesize
        return $this->extractViaPhpNative($filePath);
    }

    private function extractViaExifTool(string $filePath): ?ExifData
    {
        try {
            $process = new Process([
                'exiftool',
                '-json',
                '-n',
                '-GPSLatitude',
                '-GPSLongitude',
                '-DateTimeOriginal',
                '-CreateDate',
                '-MediaCreateDate',
                '-Make',
                '-Model',
                '-ImageWidth',
                '-ImageHeight',
                '-SourceImageWidth',
                '-SourceImageHeight',
                '-Orientation',
                '-Rotation',
                $filePath,
            ]);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = $process->getOutput();
            /** @var array<int, array<string, mixed>>|null $json */
            $json = json_decode($output, true);
            if (empty($json[0])) {
                return null;
            }

            $data = $json[0];
            $lat = isset($data['GPSLatitude']) && is_numeric($data['GPSLatitude']) ? (float) $data['GPSLatitude'] : null;
            $lon = isset($data['GPSLongitude']) && is_numeric($data['GPSLongitude']) ? (float) $data['GPSLongitude'] : null;

            $capturedAt = null;
            $rawDate = $data['DateTimeOriginal'] ?? $data['CreateDate'] ?? $data['MediaCreateDate'] ?? null;
            if (! empty($rawDate) && is_string($rawDate) && ! str_starts_with($rawDate, '0000:00:00')) {
                try {
                    $normalizedDate = preg_replace('/^(\d{4}):(\d{2}):(\d{2})/', '$1-$2-$3', $rawDate) ?? $rawDate;
                    $capturedAt = CarbonImmutable::parse($normalizedDate);
                } catch (Throwable) {
                    $capturedAt = null;
                }
            }

            $width = isset($data['ImageWidth']) && is_numeric($data['ImageWidth']) ? (int) $data['ImageWidth'] : null;
            $height = isset($data['ImageHeight']) && is_numeric($data['ImageHeight']) ? (int) $data['ImageHeight'] : null;

            if ($width === null && isset($data['SourceImageWidth']) && is_numeric($data['SourceImageWidth'])) {
                $width = (int) $data['SourceImageWidth'];
            }
            if ($height === null && isset($data['SourceImageHeight']) && is_numeric($data['SourceImageHeight'])) {
                $height = (int) $data['SourceImageHeight'];
            }

            $orientation = isset($data['Orientation']) && is_numeric($data['Orientation']) ? (int) $data['Orientation'] : null;
            $rotation = isset($data['Rotation']) && is_numeric($data['Rotation']) ? (int) $data['Rotation'] : null;

            if ($width !== null && $height !== null) {
                if (($orientation !== null && in_array($orientation, [5, 6, 7, 8], true)) || ($rotation !== null && in_array($rotation, [90, 270], true))) {
                    [$width, $height] = [$height, $width];
                }
            }

            return new ExifData(
                capturedAt: $capturedAt,
                latitude: $lat,
                longitude: $lon,
                cameraMake: isset($data['Make']) ? (string) $data['Make'] : null,
                cameraModel: isset($data['Model']) ? (string) $data['Model'] : null,
                height: $height,
                width: $width,
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function extractViaPhpNative(string $filePath): ExifData
    {
        $imageInfo = @getimagesize($filePath);
        $width = $imageInfo !== false ? $imageInfo[0] : null;
        $height = $imageInfo !== false ? $imageInfo[1] : null;
        $imageType = $imageInfo !== false ? $imageInfo[2] : null;

        $supportedExifTypes = [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM];
        $capturedAt = null;
        $latitude = null;
        $longitude = null;
        $cameraMake = null;
        $cameraModel = null;

        if ($imageType !== null && in_array($imageType, $supportedExifTypes, true)) {
            $exif = @exif_read_data($filePath, 'ANY_TAG', true);
            if ($exif !== false) {
                $dateString = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
                if ($dateString) {
                    try {
                        $capturedAt = CarbonImmutable::createFromFormat('Y:m:d H:i:s', (string) $dateString);
                    } catch (Throwable) {
                        $capturedAt = null;
                    }
                }

                [$latitude, $longitude] = $this->extractGps($exif);
                $cameraMake = isset($exif['IFD0']['Make']) ? (string) $exif['IFD0']['Make'] : null;
                $cameraModel = isset($exif['IFD0']['Model']) ? (string) $exif['IFD0']['Model'] : null;

                $orientation = $exif['IFD0']['Orientation'] ?? $exif['Orientation'] ?? null;
                if ($orientation !== null && in_array((int) $orientation, [5, 6, 7, 8], true)) {
                    [$width, $height] = [$height, $width];
                }
            }
        }

        return new ExifData(
            capturedAt: $capturedAt,
            latitude: $latitude,
            longitude: $longitude,
            cameraMake: $cameraMake,
            cameraModel: $cameraModel,
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
