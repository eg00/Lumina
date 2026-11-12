<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\MediaTag;

use App\Models\MediaTag;
use App\MoonShine\Resources\MediaTag\Pages\MediaTagDetailPage;
use App\MoonShine\Resources\MediaTag\Pages\MediaTagFormPage;
use App\MoonShine\Resources\MediaTag\Pages\MediaTagIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<MediaTag, MediaTagIndexPage, MediaTagFormPage, MediaTagDetailPage>
 */
class MediaTagResource extends ModelResource
{
    #[\Override]
    protected string $model = MediaTag::class;

    #[\Override]
    protected string $title = 'Tags';

    #[\Override]
    protected string $column = 'name';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            MediaTagIndexPage::class,
            MediaTagFormPage::class,
            MediaTagDetailPage::class,
        ];
    }
}
