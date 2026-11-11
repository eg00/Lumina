<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Media\Pages;

use App\Models\MediaFile;
use App\MoonShine\Resources\Media\MediaResource;
use App\MoonShine\Resources\MediaTag\MediaTagResource;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

/**
 * @extends FormPage<MediaResource>
 */
class MediaFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Box::make('Media Information', [
                ID::make(),
                Preview::make('Thumbnail', 'thumbnail_path', static fn (MediaFile $item): string => $item->thumbnail_path ? Storage::disk('public')->url($item->thumbnail_path) : '')->image(),
                Preview::make('File Path', 'file_path'),
                Preview::make('Media Type', 'media_type', static fn (MediaFile $item): string => $item->media_type->value)->badge(),
                Preview::make('Mime Type', 'mime_type'),
                Switcher::make('Favorite', 'is_favorite'),
                Switcher::make('Ignored', 'is_ignored'),
                Text::make('Country', 'country'),
                Text::make('City', 'city'),
                Text::make('Place Name', 'place_name'),
                BelongsToMany::make('Tags', 'tags', resource: MediaTagResource::class)->selectMode(),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [];
    }

    /**
     * @param  FormBuilder  $component
     * @return FormBuilder
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
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
