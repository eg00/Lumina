<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Media\Pages;

use App\Models\MediaFile;
use App\MoonShine\Resources\Media\MediaResource;
use App\MoonShine\Resources\MediaTag\MediaTagResource;
use Illuminate\Support\Facades\Storage;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\ModalContract;
use MoonShine\Laravel\Fields\Relationships\BelongsToMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Preview;
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
            Preview::make('Thumbnail', 'thumbnail_path', static fn (MediaFile $item): string => $item->thumbnail_path ? '<a href="'.route('media.stream', ['mediaFile' => $item->uuid]).'?v='.$item->updated_at?->timestamp.'" target="_blank" class="inline-block" title="Open Original"><img src="'.Storage::disk('public')->url($item->thumbnail_path).'?v='.$item->updated_at?->timestamp.'" class="h-44 w-auto rounded-lg object-contain border border-slate-200 dark:border-slate-700 bg-slate-900/5 dark:bg-slate-900/40 p-1 hover:opacity-90 transition shadow-sm" alt="Thumbnail" /></a>' : '-'),
            Preview::make('Preview', 'preview_path', static fn (MediaFile $item): string => $item->preview_path ? '<a href="'.route('media.stream', ['mediaFile' => $item->uuid]).'?v='.$item->updated_at?->timestamp.'" target="_blank" class="inline-block" title="Open Original"><img src="'.Storage::disk('public')->url($item->preview_path).'?v='.$item->updated_at?->timestamp.'" class="max-h-96 w-auto rounded-lg object-contain border border-slate-200 dark:border-slate-700 bg-slate-900/5 dark:bg-slate-900/40 p-1 hover:opacity-90 transition shadow-sm" alt="Preview" /></a>' : '-'),
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
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return parent::buttons()->add(
            ActionButton::make('Edit Photo')
                ->icon('pencil-square')
                ->canSee(static fn (?MediaFile $item): bool => $item instanceof MediaFile && in_array($item->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                ->inModal(
                    title: 'Image Editor',
                    content: static fn (MediaFile $item): string => (string) view('admin.media.editor-modal', ['media' => $item]),
                    name: static fn (MediaFile $item): string => 'media-editor-detail-'.$item->id,
                    builder: static fn (ModalContract $modal, ActionButtonContract $ctx): ModalContract => $modal->wide()
                )
        );
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
