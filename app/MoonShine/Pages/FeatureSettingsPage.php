<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\Setting;
use App\Services\Google\GooglePhotosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

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
        $photosService = app(GooglePhotosService::class);
        $isConnected = $photosService->isConnected();
        $userEmail = $photosService->getUserEmail();

        $hasSecret = Setting::get('google_photos.client_secret') !== null;
        $hasToken = Setting::get('google_photos.token') !== null;

        $clientSecretField = Password::make('Google Client Secret', 'google_photos_client_secret')->raw();
        if ($hasSecret) {
            $clientSecretField->placeholder('••••••••')->hint('Configured');
        } else {
            $clientSecretField->hint('Enter your Google Cloud OAuth Client Secret');
        }

        $tokenField = Password::make('Google Photos Token', 'google_photos_token')->raw();
        if ($hasToken) {
            $tokenField->placeholder('••••••••')->hint('Configured');
        } else {
            $tokenField->hint('Optional: Paste JSON token payload or raw refresh token');
        }

        $statusBadge = $isConnected
            ? Badge::make('Connected as '.($userEmail ?? 'Google Account'), Color::GREEN)
            : Badge::make('Not Connected', Color::GRAY);

        $authAction = $isConnected
            ? ActionButton::make('Disconnect Account', route('admin.google-photos.disconnect'))
                ->method('POST')
                ->error()
            : ActionButton::make('Connect with Google', route('admin.google-photos.redirect'))
                ->primary();

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

            Box::make('Google Photos Integration', [
                Flex::make([$statusBadge, $authAction])->justifyAlign('between'),
                FormBuilder::make()
                    ->asyncMethod('save', page: $this)
                    ->fields([
                        Text::make('Google Client ID', 'google_photos_client_id'),
                        $clientSecretField,
                        $tokenField,
                    ])
                    ->fill([
                        'google_photos_client_id' => (string) Setting::get('google_photos.client_id', ''),
                    ])
                    ->submit('Save Google Photos Credentials'),
            ]),
        ];
    }

    #[AsyncMethod]
    public function save(Request $request): void
    {
        if ($request->has('ai_search') || $request->has('default_sort')) {
            Setting::set('features.ai_search', $request->boolean('ai_search'));
            Setting::set('features.geo_map', $request->boolean('geo_map'));
            Setting::set('features.tags', $request->boolean('tags'));
            Setting::set('features.default_sort', (string) $request->input('default_sort', 'captured_at_desc'));
        }

        if ($request->has('google_photos_client_id')) {
            Setting::set('google_photos.client_id', (string) $request->input('google_photos_client_id'));
        }

        if ($request->filled('google_photos_client_secret')) {
            Setting::set(
                'google_photos.client_secret',
                Crypt::encryptString((string) $request->input('google_photos_client_secret'))
            );
        }

        if ($request->filled('google_photos_token')) {
            app(GooglePhotosService::class)->setManualToken((string) $request->input('google_photos_token'));
        }

        toast('Settings saved successfully', ToastType::SUCCESS);
    }
}
