<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaEmbedding;
use App\Models\MediaFile;
use App\Services\Ai\ClipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateEmbeddingBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $mediaFileIds
     */
    public function __construct(public array $mediaFileIds) {}

    /**
     * Execute the job.
     */
    public function handle(ClipService $clipService): void
    {
        $files = MediaFile::query()
            ->whereIn('id', $this->mediaFileIds)
            ->whereNotNull('preview_path')
            ->get();

        $pathMap = [];
        foreach ($files as $file) {
            if ($file->preview_path) {
                $pathMap[$file->preview_path] = $file->id;
            }
        }

        $results = $clipService->embedBatch(array_keys($pathMap));

        foreach ($results as $previewPath => $vector) {
            $fileId = $pathMap[$previewPath] ?? null;
            if ($fileId) {
                MediaEmbedding::query()->updateOrCreate(
                    ['media_file_id' => $fileId],
                    ['embedding' => $vector],
                );
            }
        }
    }
}
