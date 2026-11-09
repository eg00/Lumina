<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';

    public static function tryFromMimeType(string $mimeType): ?self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'video/') => self::Video,
            default => null,
        };
    }

    public function isImage(): bool
    {
        return $this === self::Image;
    }

    public function isVideo(): bool
    {
        return $this === self::Video;
    }
}
