<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ImportGooglePhotosBatchJob;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class GooglePhotosImportModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_session_endpoint_creates_picker_session(): void
    {
        $admin = MoonshineUser::factory()->create();

        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/sessions' => Http::response([
                'id' => 'session_xyz_789',
                'pickerUri' => 'https://photos.google.com/picker/session_xyz_789',
                'mediaItemsSet' => false,
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->postJson('/admin/google-photos/session');

        $response->assertOk();
        $response->assertJson([
            'id' => 'session_xyz_789',
            'pickerUri' => 'https://photos.google.com/picker/session_xyz_789',
            'mediaItemsSet' => false,
        ]);
    }

    public function test_session_status_endpoint_returns_session(): void
    {
        $admin = MoonshineUser::factory()->create();

        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/sessions/session_xyz_789' => Http::response([
                'id' => 'session_xyz_789',
                'pickerUri' => 'https://photos.google.com/picker/session_xyz_789',
                'mediaItemsSet' => true,
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson('/admin/google-photos/session/session_xyz_789');

        $response->assertOk();
        $response->assertJson([
            'id' => 'session_xyz_789',
            'mediaItemsSet' => true,
        ]);
    }

    public function test_items_endpoint_returns_photos_with_precomputed_import_status(): void
    {
        $admin = MoonshineUser::factory()->create();
        $source = MediaSource::factory()->create();

        MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'google_photo_id' => 'gphoto_existing_999',
        ]);

        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_token',
            'refresh_token' => 'refresh',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photoslibrary.googleapis.com/v1/mediaItems*' => Http::response([
                'mediaItems' => [
                    ['id' => 'gphoto_existing_999', 'filename' => 'already.jpg', 'baseUrl' => 'https://photo/1'],
                    ['id' => 'gphoto_new_111', 'filename' => 'new.jpg', 'baseUrl' => 'https://photo/2'],
                ],
                'nextPageToken' => 'token_page_2',
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->getJson('/admin/google-photos/items');

        $response->assertOk();
        $response->assertJsonStructure(['items', 'nextPageToken']);

        $data = $response->json();
        $this->assertTrue($data['items'][0]['is_imported']);
        $this->assertFalse($data['items'][1]['is_imported']);
        $this->assertSame('token_page_2', $data['nextPageToken']);
    }

    public function test_import_endpoint_dispatches_batch_job(): void
    {
        Queue::fake([ImportGooglePhotosBatchJob::class]);

        $admin = MoonshineUser::factory()->create();
        $source = MediaSource::factory()->create();

        $payload = [
            'media_source_id' => $source->id,
            'items' => [
                ['id' => 'gphoto_1', 'filename' => 'test1.jpg', 'baseUrl' => 'https://photo/1'],
            ],
        ];

        $response = $this->actingAs($admin, 'moonshine')
            ->postJson('/admin/google-photos/import', $payload);

        $response->assertOk();
        Queue::assertPushed(ImportGooglePhotosBatchJob::class, fn (ImportGooglePhotosBatchJob $job): bool => $job->mediaSourceId === $source->id && count($job->items) === 1);
    }

    public function test_import_endpoint_with_session_id_fetches_items_and_dispatches_batch_job(): void
    {
        Queue::fake([ImportGooglePhotosBatchJob::class]);

        $admin = MoonshineUser::factory()->create();
        $source = MediaSource::factory()->create();

        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://photospicker.googleapis.com/v1/mediaItems*' => Http::response([
                'mediaItems' => [
                    [
                        'id' => 'item_picker_1',
                        'mediaFile' => [
                            'baseUrl' => 'https://photos/picker1',
                            'filename' => 'picked.jpg',
                            'mimeType' => 'image/jpeg',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'media_source_id' => $source->id,
            'session_id' => 'sess_12345',
        ];

        $response = $this->actingAs($admin, 'moonshine')
            ->postJson('/admin/google-photos/import', $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'count' => 1,
        ]);

        Queue::assertPushed(ImportGooglePhotosBatchJob::class, fn (ImportGooglePhotosBatchJob $job): bool => $job->mediaSourceId === $source->id
            && count($job->items) === 1
            && $job->items[0]['id'] === 'item_picker_1'
            && $job->items[0]['filename'] === 'picked.jpg');
    }

    public function test_thumbnail_endpoint_proxies_google_photos_image(): void
    {
        $admin = MoonshineUser::factory()->create();

        Setting::set('google_photos.token', Crypt::encryptString((string) json_encode([
            'access_token' => 'valid_bearer_token',
            'expires_at' => now()->addHour()->getTimestamp(),
        ])));

        Http::fake([
            'https://lh3.googleusercontent.com/sample_photo*' => Http::response('fake_image_bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/google-photos/thumbnail?url='.urlencode('https://lh3.googleusercontent.com/sample_photo'));

        $response->assertOk();
        $this->assertSame('fake_image_bytes', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_thumbnail_endpoint_rejects_non_google_url(): void
    {
        $admin = MoonshineUser::factory()->create();

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/google-photos/thumbnail?url='.urlencode('https://evil.com/image.jpg'));

        $response->assertStatus(403);
    }

    public function test_media_index_page_displays_import_action_button(): void
    {
        $admin = MoonshineUser::factory()->create();

        $response = $this->actingAs($admin, 'moonshine')
            ->get('/admin/resource/media-resource/media-index-page');

        $response->assertOk();
        $response->assertSee('Import from Google Photos');
    }
}
