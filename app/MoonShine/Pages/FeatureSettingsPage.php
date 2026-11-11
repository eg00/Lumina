<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Setting;
use Illuminate\Http\Request;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

class FeatureSettingsPage extends Page
{
    #[\Override]
    protected string $title = 'Feature Settings';

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            '#' => $this->getTitle(),
        ];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        return [
            Box::make('Gallery Feature Toggles', [
                FormBuilder::make()
                    ->asyncMethod('save', page: $this)
                    ->fields([
                        Switcher::make('AI Semantic Search (CLIP)', 'ai_search'),
                        Switcher::make('World Map View (GPS)', 'geo_map'),
                        Switcher::make('Auto-Generated Tags', 'tags'),
                        Select::make('Default Sort Order', 'default_sort')
                            ->options([
                                'captured_at_desc' => 'Capture Date (Newest First)',
                                'captured_at_asc' => 'Capture Date (Oldest First)',
                                'created_at_desc' => 'Date Added (Recently Imported)',
                                'file_path_asc' => 'File Name (A-Z)',
                            ]),
                    ])
                    ->fill([
                        'ai_search' => (bool) Setting::get('features.ai_search', true),
                        'geo_map' => (bool) Setting::get('features.geo_map', true),
                        'tags' => (bool) Setting::get('features.tags', true),
                        'default_sort' => (string) Setting::get('features.default_sort', 'captured_at_desc'),
                    ])
                    ->submit('Save Settings'),
            ]),
        ];
    }

    #[AsyncMethod]
    public function save(Request $request): void
    {
        Setting::set('features.ai_search', $request->boolean('ai_search'));
        Setting::set('features.geo_map', $request->boolean('geo_map'));
        Setting::set('features.tags', $request->boolean('tags'));
        Setting::set('features.default_sort', (string) $request->input('default_sort', 'captured_at_desc'));

        toast('Feature settings saved successfully', ToastType::SUCCESS);
    }
}
