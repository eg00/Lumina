<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Google\GooglePhotosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class GooglePhotosSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_oauth_redirect(): void
    {
        $response = $this->get('/admin/google-photos/redirect');
        $response->assertRedirect();
    }

    public function test_authenticated_admin_can_initiate_oauth_flow(): void
    {
        $admin = MoonshineUser::factory()->create();
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/google-photos/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', (string) $response->headers->get('Location'));
    }

    public function test_callback_handles_authorization_and_redirects_to_settings(): void
    {
        $admin = MoonshineUser::factory()->create();
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_token',
                'refresh_token' => 'mock_refresh',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'email' => 'connected@gmail.com',
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/google-photos/callback?code=valid_code');

        $response->assertRedirect('/admin/page/feature-settings-page');
        $this->assertSame('connected@gmail.com', app(GooglePhotosService::class)->getUserEmail());
    }

    public function test_secret_values_are_never_rendered_in_settings_page_html(): void
    {
        $admin = MoonshineUser::factory()->create();
        Setting::set('google_photos.client_id', 'my-visible-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('super-secret-client-value'));
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode(['refresh_token' => 'secret-refresh-token-xyz'])));

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/page/feature-settings-page');

        $response->assertOk();
        $response->assertSee('my-visible-client-id');
        $response->assertDontSee('super-secret-client-value');
        $response->assertDontSee('secret-refresh-token-xyz');
    }

    public function test_saving_settings_with_empty_secrets_preserves_existing_secrets(): void
    {
        $admin = MoonshineUser::factory()->create();
        $encryptedSecret = Crypt::encryptString('original-client-secret');
        $encryptedToken = Crypt::encryptString((string) json_encode(['refresh_token' => 'original-token']));

        Setting::set('google_photos.client_id', 'original-client-id');
        Setting::set('google_photos.client_secret', $encryptedSecret);
        Setting::set('google_photos.token', $encryptedToken);

        $response = $this->actingAs($admin, 'moonshine')
            ->postJson('/admin/method/feature-settings-page?method=save', [
                'google_photos_client_id' => 'updated-client-id',
                'google_photos_client_secret' => '',
                'google_photos_token' => '',
            ]);

        $response->assertOk();
        $this->assertSame('updated-client-id', Setting::get('google_photos.client_id'));
        $this->assertSame($encryptedSecret, Setting::get('google_photos.client_secret'));
        $this->assertSame($encryptedToken, Setting::get('google_photos.token'));
    }

    public function test_saving_settings_with_new_secrets_updates_and_encrypts_them(): void
    {
        $admin = MoonshineUser::factory()->create();

        $response = $this->actingAs($admin, 'moonshine')
            ->postJson('/admin/method/feature-settings-page?method=save', [
                'google_photos_client_id' => 'new-client-id',
                'google_photos_client_secret' => 'new-secret-value-123',
                'google_photos_token' => 'new-refresh-token-456',
            ]);

        $response->assertOk();
        $this->assertSame('new-client-id', Setting::get('google_photos.client_id'));
        $this->assertSame('new-secret-value-123', Crypt::decryptString((string) Setting::get('google_photos.client_secret')));

        /** @var array<string, mixed> $decryptedToken */
        $decryptedToken = json_decode(Crypt::decryptString((string) Setting::get('google_photos.token')), true);
        $this->assertSame('new-refresh-token-456', $decryptedToken['refresh_token'] ?? null);
    }

    public function test_disconnect_clears_credentials_and_redirects_to_settings(): void
    {
        $admin = MoonshineUser::factory()->create();
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode(['access_token' => 'abc'])));
        Setting::set('google_photos.email', 'disconnect@example.com');

        $response = $this->actingAs($admin, 'moonshine')
            ->post('/admin/google-photos/disconnect');

        $response->assertRedirect('/admin/page/feature-settings-page');
        $this->assertFalse(app(GooglePhotosService::class)->isConnected());
        $this->assertNull(Setting::get('google_photos.token'));
    }
}
