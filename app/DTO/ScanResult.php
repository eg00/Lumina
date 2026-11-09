<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ScanResult
{
    public function __construct(
        public int $totalScanned = 0,
        public int $newCount = 0,
        public int $reusedCount = 0,
        public int $skippedCount = 0,
    ) {}

}
