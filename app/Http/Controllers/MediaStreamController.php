<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaStreamController extends Controller
{
    public function stream(MediaFile $mediaFile): BinaryFileResponse
    {
        if ($mediaFile->is_ignored === true || ! $mediaFile->mediaSource) {
            abort(404);
        }

        $realSourcePath = realpath($mediaFile->mediaSource->path);
        $realFilePath = realpath($mediaFile->file_path);

        if ($realFilePath === false) {
            abort(404);
        }

        $sourcePrefix = $realSourcePath !== false
            ? rtrim($realSourcePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR
            : null;
        if ($sourcePrefix === null || ! str_starts_with($realFilePath, $sourcePrefix)) {
            abort(403, 'Access denied.');
        }

        $headers = [
            'Content-Type' => $mediaFile->mime_type,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, private, must-revalidate',
            'ETag' => '"'.$mediaFile->file_hash.'"',
        ];

        return response()->file($realFilePath, $headers);

    }
}
