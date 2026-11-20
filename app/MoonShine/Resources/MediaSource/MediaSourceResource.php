<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MediaSource;

use App\Jobs\ScanMediaSourceJob;
use App\Models\MediaSource;
use App\MoonShine\Resources\MediaSource\Pages\MediaSourceDetailPage;
use App\MoonShine\Resources\MediaSource\Pages\MediaSourceFormPage;
use App\MoonShine\Resources\MediaSource\Pages\MediaSourceIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;

/**
 * @extends ModelResource<MediaSource, MediaSourceIndexPage, MediaSourceFormPage, MediaSourceDetailPage>
 */
class MediaSourceResource extends ModelResource
{
    #[\Override]
    protected string $model = MediaSource::class;

    #[\Override]
    protected string $title = 'Sources';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            MediaSourceIndexPage::class,
            MediaSourceFormPage::class,
            MediaSourceDetailPage::class,
        ];
    }

    #[AsyncMethod]
    public function scan(): void
    {
        $sourceId = request()->integer('resourceItem');
        $source = MediaSource::query()->findOrFail($sourceId);

        ScanMediaSourceJob::dispatch($source);

        toast("Scan started in background for {$source->name}", ToastType::INFO);
    }
}
