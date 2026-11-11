<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\FeatureSettingsPage;
use App\MoonShine\Resources\Media\MediaResource;
use App\MoonShine\Resources\MediaSource\MediaSourceResource;
use App\MoonShine\Resources\MediaTag\MediaTagResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                MediaResource::class,
                MediaSourceResource::class,
                MediaTagResource::class,
            ])
            ->pages([
                ...$core->getConfig()->getPages(),
                FeatureSettingsPage::class,
            ]);
    }
}
