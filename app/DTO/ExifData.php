<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\CarbonImmutable;

final readonly class ExifData
{
    public function __construct(
        public ?CarbonImmutable $capturedAt = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $cameraMake = null,
        public ?string $cameraModel = null,
        public ?int $height = null,
        public ?int $width = null,
    ) {}

}
