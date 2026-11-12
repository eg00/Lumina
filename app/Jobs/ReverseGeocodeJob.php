<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\Geo\ReverseGeocodeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReverseGeocodeJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public function __construct(public MediaFile $mediaFile)
    {
        //
    }

    public function handle(ReverseGeocodeService $geoService): void
    {
        $file = $this->mediaFile;

        if ($file->latitude === null || $file->longitude === null) {
            return;
        }

        $geoData = $geoService->reverse($file->latitude, $file->longitude);

        if ($geoData !== null) {
            $file->update([
                'country' => $geoData['country'] ?? $file->country,
                'city' => $geoData['city'] ?? $file->city,
                'place_name' => $geoData['place_name'] ?? $file->place_name,
            ]);
        }
    }
}
