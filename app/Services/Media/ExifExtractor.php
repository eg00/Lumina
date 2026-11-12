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
                $cameraMake = $exif['IFD0']['Make'] ?? null;
                $cameraModel = $exif['IFD0']['Model'] ?? null;
            }
        }

        // Fallback на ExifTool: если PHP не смог прочитать GPS (из-за Illegal IFD offset или неподдерживаемого формата)
        if ($latitude === null || $longitude === null) {
            $exifToolData = $this->extractViaExifTool($filePath);
            if ($exifToolData instanceof ExifData) {
                $latitude ??= $exifToolData->latitude;
                $longitude ??= $exifToolData->longitude;
                $capturedAt ??= $exifToolData->capturedAt;
                $cameraMake ??= $exifToolData->cameraMake;
                $cameraModel ??= $exifToolData->cameraModel;
                $width ??= $exifToolData->width;
                $height ??= $exifToolData->height;
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
                '-Make',
                '-Model',
                '-ImageWidth',
                '-ImageHeight',
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
            if (! empty($data['DateTimeOriginal'])) {
                try {
                    $capturedAt = CarbonImmutable::createFromFormat('Y:m:d H:i:s', (string) $data['DateTimeOriginal']);
                } catch (Throwable) {
                    $capturedAt = null;
                }
            }

            return new ExifData(
                capturedAt: $capturedAt,
                latitude: $lat,
                longitude: $lon,
                cameraMake: isset($data['Make']) ? (string) $data['Make'] : null,
                cameraModel: isset($data['Model']) ? (string) $data['Model'] : null,
                height: isset($data['ImageHeight']) && is_numeric($data['ImageHeight']) ? (int) $data['ImageHeight'] : null,
                width: isset($data['ImageWidth']) && is_numeric($data['ImageWidth']) ? (int) $data['ImageWidth'] : null,
            );
        } catch (Throwable) {
            return null;
        }
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
