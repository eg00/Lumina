<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\MediaImportProgressEvent;
use App\Jobs\ImportGooglePhotosBatchJob;
use App\Jobs\ProcessMediaFileJob;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Services\Google\GooglePhotosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class GooglePhotosImportJobTest extends TestCase
{
    use RefreshDatabase;

    private string $tempStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempStorage = storage_path('framework/testing/photos_import');
        File::ensureDirectoryExists($this->tempStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempStorage);
        parent::tearDown();
    }

    public function test_downloads_unique_photo_and_dispatches_pipeline(): void
    {
        Queue::fake([ProcessMediaFileJob::class]);
        Event::fake([MediaImportProgressEvent::class]);

        $source = MediaSource::factory()->create(['path' => $this->tempStorage]);

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake([
            'https://lh3.googleusercontent.com/test-photo=d' => Http::response($imageBytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $items = [
            [
                'id' => 'gphoto_unique_001',
                'filename' => 'sunset.png',
                'baseUrl' => 'https://lh3.googleusercontent.com/test-photo',
                'mimeType' => 'image/png',
                'creationTime' => '2026-08-15T10:30:00Z',
            ],
        ];

        $job = new ImportGooglePhotosBatchJob($items, $source->id);
        $job->handle(app(GooglePhotosService::class));

        $this->assertDatabaseHas('media_files', [
            'google_photo_id' => 'gphoto_unique_001',
            'media_source_id' => $source->id,
        ]);

        Queue::assertPushed(ProcessMediaFileJob::class, 1);
        Event::assertDispatched(MediaImportProgressEvent::class);
    }

    public function test_deduplicates_existing_hash_by_updating_google_photo_id(): void
    {
        Queue::fake([ProcessMediaFileJob::class]);
        Event::fake([MediaImportProgressEvent::class]);

        $source = MediaSource::factory()->create(['path' => $this->tempStorage]);

        $imageBytes = 'duplicate-test-content';
        $hash = hash('sha256', $imageBytes);

        $existingFile = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'file_hash' => $hash,
            'google_photo_id' => null,
        ]);

        Http::fake([
            'https://lh3.googleusercontent.com/dup=d' => Http::response($imageBytes, 200),
        ]);

        $items = [
            [
                'id' => 'gphoto_dup_002',
                'filename' => 'existing.png',
                'baseUrl' => 'https://lh3.googleusercontent.com/dup',
                'mimeType' => 'image/png',
                'creationTime' => '2026-08-15T10:30:00Z',
            ],
        ];

        $job = new ImportGooglePhotosBatchJob($items, $source->id);
        $job->handle(app(GooglePhotosService::class));

        $existingFile->refresh();
        $this->assertSame('gphoto_dup_002', $existingFile->google_photo_id);
        Queue::assertNotPushed(ProcessMediaFileJob::class);
        Event::assertDispatched(MediaImportProgressEvent::class);
    }

    public function test_filename_collision_appends_hash_suffix(): void
    {
        Queue::fake([ProcessMediaFileJob::class]);
        Event::fake([MediaImportProgressEvent::class]);

        $source = MediaSource::factory()->create(['path' => $this->tempStorage]);

        // Create an existing file at the exact target path (2026/08/sunset.png)
        $targetDir = $this->tempStorage.'/2026/08';
        File::ensureDirectoryExists($targetDir);
        File::put($targetDir.'/sunset.png', 'existing-file-content');

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $hash = hash('sha256', $imageBytes);

        Http::fake([
            'https://lh3.googleusercontent.com/test-photo=d' => Http::response($imageBytes, 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $items = [
            [
                'id' => 'gphoto_collision_003',
                'filename' => 'sunset.png',
                'baseUrl' => 'https://lh3.googleusercontent.com/test-photo',
                'mimeType' => 'image/png',
                'creationTime' => '2026-08-15T10:30:00Z',
            ],
        ];

        $job = new ImportGooglePhotosBatchJob($items, $source->id);
        $job->handle(app(GooglePhotosService::class));

        // The newly created file should have a suffix with the hash prefix
        $expectedCollisionPath = $targetDir.'/sunset_'.substr($hash, 0, 8).'.png';
        $this->assertFileExists($expectedCollisionPath);

        $this->assertDatabaseHas('media_files', [
            'google_photo_id' => 'gphoto_collision_003',
            'file_path' => $expectedCollisionPath,
        ]);
    }

    public function test_failed_download_cleans_up_and_dispatches_failed_event(): void
    {
        Queue::fake([ProcessMediaFileJob::class]);
        Event::fake([MediaImportProgressEvent::class]);

        $source = MediaSource::factory()->create(['path' => $this->tempStorage]);

        Http::fake([
            'https://lh3.googleusercontent.com/broken=d' => Http::response('Server Error', 500),
        ]);

        $items = [
            [
                'id' => 'gphoto_failed_004',
                'filename' => 'broken.png',
                'baseUrl' => 'https://lh3.googleusercontent.com/broken',
                'mimeType' => 'image/png',
                'creationTime' => '2026-08-15T10:30:00Z',
            ],
        ];

        $job = new ImportGooglePhotosBatchJob($items, $source->id);
        $job->handle(app(GooglePhotosService::class));

        $this->assertDatabaseMissing('media_files', [
            'google_photo_id' => 'gphoto_failed_004',
        ]);

        Queue::assertNotPushed(ProcessMediaFileJob::class);
        Event::assertDispatched(MediaImportProgressEvent::class, fn (MediaImportProgressEvent $event): bool => $event->status === 'failed');
    }
}
