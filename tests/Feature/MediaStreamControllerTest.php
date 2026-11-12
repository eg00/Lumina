<?php

declare(strict_types=1);

use App\Models\MediaFile;
use App\Models\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

test('streaming active media returns binary file response with byte ranges and correct mime', function (): void {
    $tempDir = sys_get_temp_dir().'/test_stream_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/clip.mp4';
    file_put_contents($filePath, 'sample-mp4-video-stream-content');

    $source = MediaSource::query()->create(['name' => 'NAS', 'path' => $tempDir, 'is_active' => true]);
    $file = MediaFile::factory()->create([
        'media_source_id' => $source->id,
        'file_path' => $filePath,
        'mime_type' => 'video/mp4',
        'is_ignored' => false,
    ]);

    $response = test()->get(route('media.stream', $file->uuid));

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Accept-Ranges', 'bytes');

    File::delete($filePath);
    rmdir($tempDir);
});

test('path traversal or escaping source root returns 403 forbidden', function (): void {
    $source = MediaSource::query()->create(['name' => 'NAS', 'path' => '/media/nas', 'is_active' => true]);
    $file = MediaFile::factory()->create([
        'media_source_id' => $source->id,
        'file_path' => '/etc/passwd',
        'is_ignored' => false,
    ]);

    test()->get(route('media.stream', $file->uuid))->assertStatus(403);
});

test('streaming ignored media returns 404', function (): void {
    $file = MediaFile::factory()->create(['is_ignored' => true]);
    test()->get(route('media.stream', $file->uuid))->assertStatus(404);
});
