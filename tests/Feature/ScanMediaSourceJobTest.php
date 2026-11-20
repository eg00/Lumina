<?php

declare(strict_types=1);

use App\Jobs\ScanMediaSourceJob;
use App\Models\MediaSource;
use App\Services\Scanner\DirectoryScanner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use MoonShine\Laravel\Models\MoonshineUser;

uses(RefreshDatabase::class);

test('ScanMediaSourceJob executes directory scanner for media source', function (): void {
    $tempDir = sys_get_temp_dir().'/test_scan_job_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/photo.jpg';
    copy(base_path('tests/fixtures/sample_with_gps.jpg'), $filePath);

    $source = MediaSource::factory()->create([
        'name' => 'Background Source',
        'path' => $tempDir,
        'is_active' => true,
    ]);

    $job = new ScanMediaSourceJob($source);
    expect($job->uniqueId())->toBe((string) $source->id);

    $job->handle(app(DirectoryScanner::class));

    $this->assertDatabaseHas('media_files', [
        'media_source_id' => $source->id,
        'file_path' => $filePath,
    ]);

    File::delete($filePath);
    @rmdir($tempDir);
});

test('admin trigger scan dispatches ScanMediaSourceJob to queue', function (): void {
    Queue::fake([ScanMediaSourceJob::class]);

    $admin = MoonshineUser::factory()->create();
    $source = MediaSource::factory()->create([
        'name' => 'Queued Source',
        'is_active' => true,
    ]);

    $url = '/admin/method/media-source-index-page/media-source-resource?method=scan&resourceItem='.$source->id;

    $this->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine')
        ->get($url)
        ->assertSuccessful();

    Queue::assertPushed(ScanMediaSourceJob::class, function (ScanMediaSourceJob $job) use ($source): bool {
        return $job->mediaSource->id === $source->id;
    });
});
