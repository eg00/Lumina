<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaSource;
use App\Services\Scanner\DirectoryScanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScanMediaSourceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public function __construct(public MediaSource $mediaSource) {}

    public function uniqueId(): string
    {
        return (string) $this->mediaSource->id;
    }

    public function handle(DirectoryScanner $scanner): void
    {
        Log::info("ScanMediaSourceJob: starting scan for source #{$this->mediaSource->id} ({$this->mediaSource->name}) at {$this->mediaSource->path}");

        $result = $scanner->scan($this->mediaSource);

        Log::info("ScanMediaSourceJob: finished scan for source #{$this->mediaSource->id}. Scanned: {$result->totalScanned}, New: {$result->newCount}, Reused: {$result->reusedCount}, Skipped: {$result->skippedCount}");
    }
}
