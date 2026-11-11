<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Media\Pages;

use App\Models\MediaFile;
use App\MoonShine\Resources\Media\MediaResource;
use App\MoonShine\Resources\MediaTag\MediaTagResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends DetailPage<MediaResource>
 */
class MediaDetailPage extends DetailPage
{
    /**
     * @return list<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            Image::make('Thumbnail', 'thumbnail_path')->disk('public'),
            Image::make('Preview', 'preview_path')->disk('public'),
            Text::make('File Path', 'file_path'),
            Text::make('Media Type', 'media_type', static fn (MediaFile $item): string => $item->media_type->value)->badge(),
            Text::make('Mime Type', 'mime_type'),
            Text::make('Width', 'width'),
            Text::make('Height', 'height'),
            Text::make('Dominant Color', 'dominant_color'),
            Text::make('Captured At', 'captured_at'),
            Text::make('Latitude', 'latitude'),
            Text::make('Longitude', 'longitude'),
            Text::make('Country', 'country'),
            Text::make('City', 'city'),
            Text::make('Place Name', 'place_name'),
            Switcher::make('Favorite', 'is_favorite'),
            Switcher::make('Ignored', 'is_ignored'),
            BelongsToMany::make('Tags', 'tags', resource: MediaTagResource::class),
        ];
    }

    /**
     * @param  TableBuilder  $component
     * @return TableBuilder
     */
    protected function modifyDetailComponent(ComponentContract $component): ComponentContract
    {
        return $component;
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function topLayer(): array
    {
        return [
            ...parent::topLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            ...parent::mainLayer(),
        ];
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function bottomLayer(): array
    {
        return [
            ...parent::bottomLayer(),
        ];
    }
}
