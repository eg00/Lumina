<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Events\MediaScanProgressEvent;
use App\Jobs\ProcessMediaFileJob;
use App\Models\MediaEmbedding;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Services\Scanner\DirectoryScanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;

uses(RefreshDatabase::class);

test('scanner indexes new files, broadcasts mercure progress and dispatches processing jobs', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/photo1.jpg';
    file_put_contents($filePath, 'fake-image-content-unique');

    $source = MediaSource::query()->create([
        'name' => 'Test Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    $scanner = app(DirectoryScanner::class);
    $result = $scanner->scan($source);

    expect($result->newCount)->toBe(1)
        ->and($result->totalScanned)->toBe(1);

    $this->assertDatabaseHas('media_files', [
        'file_path' => $filePath,
        'media_source_id' => $source->id,
    ]);

    Queue::assertPushed(ProcessMediaFileJob::class);
    Event::assertDispatched(MediaScanProgressEvent::class);

    File::delete($filePath);
    rmdir($tempDir);
});

test('scanner reuses previews and embeddings when file hash matches existing photo', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_dup_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/duplicate.jpg';
    $content = 'identical-binary-bytes';
    file_put_contents($filePath, $content);
    $hash = hash('sha256', $content);

    /** @var MediaFile $existing */
    $existing = MediaFile::factory()->create([
        'file_hash' => $hash,
        'preview_path' => 'previews/existing_1280.webp',
        'thumbnail_path' => 'thumbnails/existing_320.webp',
        'dominant_color' => '#123456',
        'width' => 1920,
        'height' => 1080,
    ]);

    $vector = array_fill(0, 512, 0.1);
    MediaEmbedding::query()->create([
        'media_file_id' => $existing->id,
        'embedding' => json_encode($vector),
    ]);

    $source = MediaSource::query()->create([
        'name' => 'Dup Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    $scanner = app(DirectoryScanner::class);
    $result = $scanner->scan($source);

    expect($result->newCount)->toBe(1)
        ->and($result->reusedCount)->toBe(1);

    Queue::assertNothingPushed();

    /** @var MediaFile|null $newFile */
    $newFile = MediaFile::query()->where('file_path', $filePath)->first();
    expect($newFile)->not->toBeNull()
        ->and($newFile?->preview_path)->toBe('previews/existing_1280.webp')
        ->and($newFile?->thumbnail_path)->toBe('thumbnails/existing_320.webp')
        ->and($newFile?->dominant_color)->toBe('#123456')
        ->and($newFile?->width)->toBe(1920)
        ->and($newFile?->height)->toBe(1080);

    $this->assertDatabaseHas('media_embeddings', [
        'media_file_id' => $newFile?->id,
    ]);

    File::delete($filePath);
    rmdir($tempDir);
});

test('scanner does not reimport ignored files', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_ignored_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/ignored.jpg';
    file_put_contents($filePath, 'fake-content');

    $source = MediaSource::query()->create([
        'name' => 'Ignored Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    MediaFile::query()->create([
        'media_source_id' => $source->id,
        'file_path' => $filePath,
        'storage_disk' => 'local',
        'file_hash' => hash('sha256', 'fake-content'),
        'file_size' => 12,
        'mime_type' => 'image/jpeg',
        'media_type' => MediaType::Image,
        'mtime' => now(),
        'is_ignored' => true,
    ]);

    $scanner = app(DirectoryScanner::class);
    $result = $scanner->scan($source);

    expect($result->newCount)->toBe(0);
    Queue::assertNothingPushed();

    File::delete($filePath);
    rmdir($tempDir);
});

test('scanner skips unmodified files when mtime matches', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_mtime_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/existing.jpg';
    file_put_contents($filePath, 'same-content');
    $mtime = filemtime($filePath);
    if ($mtime === false) {
        throw new RuntimeException('Cannot get filemtime');
    }

    $source = MediaSource::query()->create([
        'name' => 'Mtime Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    MediaFile::query()->create([
        'media_source_id' => $source->id,
        'file_path' => $filePath,
        'storage_disk' => 'local',
        'file_hash' => hash('sha256', 'same-content'),
        'file_size' => 12,
        'mime_type' => 'image/jpeg',
        'media_type' => MediaType::Image,
        'mtime' => CarbonImmutable::createFromTimestamp($mtime),
        'is_ignored' => false,
    ]);

    $scanner = app(DirectoryScanner::class);
    $result = $scanner->scan($source);

    expect($result->newCount)->toBe(0)
        ->and($result->skippedCount)->toBe(1);

    Queue::assertNothingPushed();

    File::delete($filePath);
    rmdir($tempDir);
});

test('artisan media:scan command scans active sources and displays summary', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_cmd_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/cli_test.jpg';
    file_put_contents($filePath, 'cli-image-content');

    $source = MediaSource::query()->create([
        'name' => 'CLI Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    /** @var PendingCommand $command */
    $command = $this->artisan('media:scan');
    $command->expectsOutputToContain('Total Scanned')
        ->assertSuccessful()
        ->run();

    $this->assertDatabaseHas('media_files', [
        'file_path' => $filePath,
        'media_source_id' => $source->id,
    ]);

    File::delete($filePath);
    rmdir($tempDir);
});

test('artisan media:scan command handles specific source_id argument', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_single_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/single.jpg';
    file_put_contents($filePath, 'single-content');

    $source = MediaSource::query()->create([
        'name' => 'Single Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    /** @var PendingCommand $command */
    $command = $this->artisan('media:scan', ['source_id' => $source->id]);
    $command->expectsOutputToContain('Total Scanned')
        ->assertSuccessful()
        ->run();

    File::delete($filePath);
    rmdir($tempDir);
});

test('scanner ignores thumbnails and previews subdirectories', function (): void {
    Queue::fake();
    Event::fake([MediaScanProgressEvent::class]);

    $tempDir = sys_get_temp_dir().'/test_scan_subdirs_'.uniqid('', true);
    mkdir($tempDir);
    mkdir($tempDir.'/thumbnails');
    mkdir($tempDir.'/previews');

    file_put_contents($tempDir.'/original.jpg', 'fake-original-photo');
    file_put_contents($tempDir.'/thumbnails/thumb_320.webp', 'fake-thumbnail');
    file_put_contents($tempDir.'/previews/prev_1280.webp', 'fake-preview');

    $source = MediaSource::query()->create([
        'name' => 'Subdirs Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    $scanner = app(DirectoryScanner::class);
    $result = $scanner->scan($source);

    expect($result->totalScanned)->toBe(1)
        ->and($result->newCount)->toBe(1);

    $this->assertDatabaseHas('media_files', [
        'file_path' => realpath($tempDir.'/original.jpg'),
        'media_source_id' => $source->id,
    ]);

    $this->assertDatabaseMissing('media_files', [
        'file_path' => realpath($tempDir.'/thumbnails/thumb_320.webp'),
    ]);

    $this->assertDatabaseMissing('media_files', [
        'file_path' => realpath($tempDir.'/previews/prev_1280.webp'),
    ]);

    File::deleteDirectory($tempDir);
});
