<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Media;

use App\Jobs\GenerateEmbeddingBatchJob;
use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Services\Media\ImageEditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ImageEditorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_rotates_and_crops_image(): void
    {
        Queue::fake();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_img_').'.jpg';
        $im = imagecreatetruecolor(200, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        if ($white !== false) {
            imagefill($im, 0, 0, $white);
        }

        imagejpeg($im, $tempPath);

        $source = MediaSource::factory()->create();
        $media = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'file_path' => $tempPath,
            'file_hash' => hash_file('sha256', $tempPath),
            'mime_type' => 'image/jpeg',
            'width' => 200,
            'height' => 100,
            'file_size' => filesize($tempPath),
        ]);

        $service = app(ImageEditorService::class);
        $result = $service->apply(
            mediaFile: $media,
            rotation: 90,
            crop: ['x' => 0, 'y' => 0, 'width' => 50, 'height' => 50],
            portraitMode: false,
            blurRadius: 0
        );

        $this->assertEquals(50, $result->width);
        $this->assertEquals(50, $result->height);
        $this->assertFileExists($tempPath);
        Queue::assertPushed(GenerateEmbeddingBatchJob::class);
        File::delete($tempPath);
    }

    public function test_apply_with_portrait_mode_calls_ai_worker(): void
    {
        Queue::fake();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_img_').'.jpg';
        $im = imagecreatetruecolor(100, 100);
        $white = imagecolorallocate($im, 255, 255, 255);
        if ($white !== false) {
            imagefill($im, 0, 0, $white);
        }

        imagejpeg($im, $tempPath);

        $fakeBlurredBytes = (string) file_get_contents($tempPath);
        Http::fake([
            '*/portrait-blur' => Http::response($fakeBlurredBytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $source = MediaSource::factory()->create();
        $media = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'file_path' => $tempPath,
            'file_hash' => hash_file('sha256', $tempPath),
            'mime_type' => 'image/jpeg',
            'width' => 100,
            'height' => 100,
            'file_size' => filesize($tempPath),
        ]);

        $service = app(ImageEditorService::class);
        $result = $service->apply(
            mediaFile: $media,
            rotation: 0,
            crop: ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100],
            portraitMode: true,
            blurRadius: 25
        );

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/portrait-blur'));
        $this->assertEquals(100, $result->width);
        File::delete($tempPath);
    }
}
