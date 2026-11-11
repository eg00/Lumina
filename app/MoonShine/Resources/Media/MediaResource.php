<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Media;

use App\Models\MediaFile;
use App\MoonShine\Resources\Media\Pages\MediaDetailPage;
use App\MoonShine\Resources\Media\Pages\MediaFormPage;
use App\MoonShine\Resources\Media\Pages\MediaIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<MediaFile, MediaIndexPage, MediaFormPage, MediaDetailPage>
 */
class MediaResource extends ModelResource
{
    #[\Override]
    protected string $model = MediaFile::class;

    #[\Override]
    protected string $title = 'Media';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            MediaIndexPage::class,
            MediaFormPage::class,
            MediaDetailPage::class,
        ];
    }

    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        $item->getOriginal()->update(['is_ignored' => true]);

        return $item;
    }

    /**
     * @param  list<int|string>  $ids
     */
    protected function beforeMassDeleting(array $ids): void
    {
        MediaFile::query()->whereIn('id', $ids)->update(['is_ignored' => true]);
    }
}
