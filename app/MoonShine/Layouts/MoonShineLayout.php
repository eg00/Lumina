<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\FeatureSettingsPage;
use App\MoonShine\Resources\Media\MediaResource;
use App\MoonShine\Resources\MediaSource\MediaSourceResource;
use App\MoonShine\Resources\MediaTag\MediaTagResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    #[\Override]
    protected ?string $palette = NeutralPalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuGroup::make('Gallery', [
                MenuItem::make(MediaResource::class, 'Media Files')->icon('photo'),
                MenuItem::make(MediaSourceResource::class, 'Sources')->icon('folder'),
                MenuItem::make(MediaTagResource::class, 'Tags')->icon('hashtag'),
            ]),
            MenuItem::make(FeatureSettingsPage::class, 'Settings')->icon('adjustments-vertical'),
            MenuItem::make(MediaTagResource::class, 'Tags'),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
