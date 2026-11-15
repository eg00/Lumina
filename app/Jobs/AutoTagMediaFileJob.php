<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TagType;
use App\Models\MediaFile;
use App\Models\MediaTag;
use App\Services\Ai\ClipService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutoTagMediaFileJob implements ShouldQueue
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

    public function handle(ClipService $clipService): void
    {
        $file = $this->mediaFile;

        if (! $file->preview_path) {
            return;
        }

        $candidateTags = MediaTag::query()->pluck('name')->all();

        if ($candidateTags === []) {
            return;
        }

        $fullPreviewPath = Storage::disk('public')->path($file->preview_path);

        $matched = $clipService->classifyTags($fullPreviewPath, $candidateTags);

        $tagIds = [];
        foreach ($matched as $entry) {
            $tagName = null;
            if (is_array($entry)) {
                $tagName = $entry['tag'] ?? $entry['name'] ?? $entry['label'] ?? null;
            } elseif (is_string($entry)) {
                $tagName = $entry;
            }

            if (! is_string($tagName) || $tagName === '') {
                continue;
            }

            $tag = MediaTag::query()->where('name', $tagName)->first();
            if ($tag === null) {
                $tag = MediaTag::create([
                    'name' => $tagName,
                    'slug' => Str::slug($tagName),
                    'type' => TagType::AiDetected,
                    'is_public' => false,
                ]);
            }

            $tagIds[] = $tag->id;
        }

        if ($tagIds !== []) {
            $file->tags()->syncWithoutDetaching($tagIds);
        }
    }
}
