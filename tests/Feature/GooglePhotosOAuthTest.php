<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Google\GooglePhotosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GooglePhotosOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_correct_oauth_authorization_url(): void
    {
        Setting::set('google_photos.client_id', 'test-client-id.apps.googleusercontent.com');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));

        $service = app(GooglePhotosService::class);
        $url = $service->getAuthUrl();

        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('client_id=test-client-id.apps.googleusercontent.com', $url);
        $this->assertStringContainsString('photospicker.mediaitems.readonly', $url);
        $this->assertStringContainsString('access_type=offline', $url);
    }

    public function test_handles_callback_stores_encrypted_tokens_and_email(): void
    {
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_access_token_123',
                'refresh_token' => 'mock_refresh_token_456',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
                'email' => 'lumina-admin@example.com',
            ], 200),
        ]);

        $service = app(GooglePhotosService::class);
        $result = $service->handleCallback('mock_auth_code');

        $this->assertSame('mock_access_token_123', $result['access_token']);
        $this->assertTrue($service->isConnected());
        $this->assertSame('lumina-admin@example.com', $service->getUserEmail());
        $this->assertSame('mock_access_token_123', $service->getValidAccessToken());
    }

    public function test_set_manual_token_accepts_raw_refresh_token_and_json(): void
    {
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));

        $service = app(GooglePhotosService::class);

        // Test raw refresh token string
        $service->setManualToken('raw_refresh_token_value_789');
        $this->assertTrue($service->isConnected());

        // Test JSON payload
        $service->setManualToken(json_encode([
            'access_token' => 'manual_access_token',
            'refresh_token' => 'manual_refresh_token',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR));
        $this->assertTrue($service->isConnected());
        $this->assertSame('manual_access_token', $service->getValidAccessToken());
    }

    public function test_disconnect_clears_stored_credentials(): void
    {
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode(['access_token' => 'abc', 'refresh_token' => 'def'])));
        Setting::set('google_photos.email', 'test@example.com');

        $service = app(GooglePhotosService::class);
        $this->assertTrue($service->isConnected());

        $service->disconnect();

        $this->assertFalse($service->isConnected());
        $this->assertNull($service->getUserEmail());
    }

    public function test_refreshes_expired_access_token_automatically(): void
    {
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'expired_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_at' => now()->subMinutes(5)->getTimestamp(),
        ])));

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fresh_access_token_999',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $service = app(GooglePhotosService::class);
        $freshToken = $service->getValidAccessToken();

        $this->assertSame('fresh_access_token_999', $freshToken);
    }

    public function test_creates_picker_session(): void
    {
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_bearer_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/sessions' => Http::response([
                'id' => 'mock_session_123',
                'pickerUri' => 'https://photos.google.com/picker/mock_session_123',
                'mediaItemsSet' => false,
            ], 200),
        ]);

        $service = app(GooglePhotosService::class);
        $session = $service->createSession();

        $this->assertSame('mock_session_123', $session['id']);
        $this->assertSame('https://photos.google.com/picker/mock_session_123', $session['pickerUri']);
        $this->assertFalse($session['mediaItemsSet']);
    }

    public function test_gets_picker_session_status(): void
    {
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_bearer_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/sessions/mock_session_123' => Http::response([
                'id' => 'mock_session_123',
                'pickerUri' => 'https://photos.google.com/picker/mock_session_123',
                'mediaItemsSet' => true,
            ], 200),
        ]);

        $service = app(GooglePhotosService::class);
        $session = $service->getSession('mock_session_123');

        $this->assertSame('mock_session_123', $session['id']);
        $this->assertTrue($session['mediaItemsSet']);
    }

    public function test_list_media_items_calls_google_photos_picker_api(): void
    {
        Setting::set('google_photos.client_id', 'test-client-id');
        Setting::set('google_photos.client_secret', Crypt::encryptString('test-client-secret'));
        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_bearer_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/mediaItems*' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'item_1',
                        'type' => 'PHOTO',
                        'mediaFile' => [
                            'filename' => 'photo1.jpg',
                            'baseUrl' => 'https://lh3.googleusercontent.com/test',
                            'mimeType' => 'image/jpeg',
                        ],
                    ],
                ],
                'nextPageToken' => 'next_token_abc',
            ], 200),
        ]);

        $service = app(GooglePhotosService::class);
        $result = $service->listPickerMediaItems('session_123', pageSize: 25);

        $this->assertArrayHasKey('mediaItems', $result);
        $this->assertCount(1, $result['mediaItems']);
        $this->assertSame('item_1', $result['mediaItems'][0]['id']);
        $this->assertSame('photo1.jpg', $result['mediaItems'][0]['filename']);
        $this->assertSame('https://lh3.googleusercontent.com/test', $result['mediaItems'][0]['baseUrl']);
        $this->assertSame('next_token_abc', $result['nextPageToken']);
    }
}
