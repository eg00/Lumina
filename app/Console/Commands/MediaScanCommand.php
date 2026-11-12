<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTO\ScanResult;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Services\Scanner\DirectoryScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:scan {source_id? : Optional ID of specific MediaSource to scan} {--clean-thumbnails : Clean up mistakenly indexed thumbnails and previews from media_files}')]
#[Description('Scan media sources for new, modified, or duplicate media files')]
class MediaScanCommand extends Command
{
    public function handle(DirectoryScanner $scanner): int
    {
        if ($this->option('clean-thumbnails')) {
            $deleted = MediaFile::query()
                ->where('file_path', 'like', '%/thumbnails/%')
                ->orWhere('file_path', 'like', '%/previews/%')
                ->forceDelete();
            $this->info("Cleaned up {$deleted} invalid thumbnail/preview records from database.");
        }

        $sourceId = $this->argument('source_id');
        if ($sourceId !== null) {
            $source = MediaSource::query()->findOrFail((int) $sourceId);
            $result = $scanner->scan($source);
            $this->showResult($result);

            return self::SUCCESS;
        }

        $sources = MediaSource::query()->where('is_active', true)->get();
        if ($sources->isEmpty()) {
            $this->warn('No active media sources found.');

            return self::SUCCESS;
        }

        $sources->each(function (MediaSource $source) use ($scanner): void {
            $result = $scanner->scan($source);
            $this->info("Scanning: {$source->name} ({$source->path})");
            $this->showResult($result);
        });

        return self::SUCCESS;
    }

    private function showResult(ScanResult $result): void
    {
        $this->table(
            ['Total Scanned', 'New Files', 'Reused (Duplicates)', 'Skipped'],
            [[$result->totalScanned, $result->newCount, $result->reusedCount, $result->skippedCount]]
        );
    }
}
