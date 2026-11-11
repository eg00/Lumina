<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PreviewGenerator
{
    public function generateWebp(string $sourcePath, string $targetRelativePath, int $maxWidth): string
    {
        ini_set('memory_limit', '512M');

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new RuntimeException('Unable to read file: '.$sourcePath);
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new RuntimeException("Unable to read image from $sourcePath");
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);
        if ($origWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round(($origHeight / $origWidth) * $maxWidth);
        } else {
            $newHeight = $origHeight;
            $newWidth = $origWidth;
        }

        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        ob_start();
        imagewebp($canvas, null, 82);
        $webpData = ob_get_clean();

        Storage::disk('public')->put($targetRelativePath, (string) $webpData);

        return $targetRelativePath;
    }

    public function extractDominantColor(string $sourcePath): string
    {
        ini_set('memory_limit', '512M');

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            throw new RuntimeException("Unable to read file from $sourcePath");
        }

        $image = @imagecreatefromstring($content);
        if ($image === false) {
            throw new RuntimeException("Unable to read image from $sourcePath");
        }

        $canvas = imagecreatetruecolor(1, 1);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
        $rgb = imagecolorat($canvas, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    public function extractVideoPoster(string $videoPath): string
    {
        $tempJpg = sys_get_temp_dir().'/'.uniqid('poster_', true).'.jpg';
        Process::run([
            'ffmpeg', '-y',
            '-ss', '00:00:01.000',
            '-i', $videoPath,
            '-vframes', '1',
            '-f', 'image2',
            $tempJpg,
        ]);

        if (! file_exists($tempJpg) || filesize($tempJpg) === 0) {
            Process::run(['ffmpeg', '-y', '-ss', '00:00:00.000', '-i', $videoPath, '-vframes', '1', '-f', 'image2', $tempJpg]);
        }

        return $tempJpg;
    }
}
