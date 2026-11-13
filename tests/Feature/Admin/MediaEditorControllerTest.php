<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MediaFile;
use App\Models\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class MediaEditorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'name' => 'Admin',
            'email' => uniqid('admin_', true).'@example.com',
            'password' => bcrypt('secret'),
            'moonshine_user_role_id' => 1,
        ]);
    }

    public function test_rejects_unsupported_mime_type(): void
    {
        $admin = $this->createAdmin();
        $source = MediaSource::factory()->create();
        $media = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'mime_type' => 'video/mp4',
        ]);

        $response = $this->actingAs($admin, 'moonshine')->postJson("/admin/media/{$media->id}/apply-edit", [
            'rotation' => 90,
            'crop' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10],
            'portrait_mode' => false,
            'blur_radius' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_apply_edit_successful(): void
    {
        $admin = $this->createAdmin();
        $tempPath = tempnam(sys_get_temp_dir(), 'ctrl_test_').'.jpg';
        $im = imagecreatetruecolor(100, 100);
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
            'width' => 100,
            'height' => 100,
            'file_size' => filesize($tempPath),
        ]);

        $response = $this->actingAs($admin, 'moonshine')->postJson("/admin/media/{$media->id}/apply-edit", [
            'rotation' => 90,
            'crop' => ['x' => 0, 'y' => 0, 'width' => 50, 'height' => 50],
            'portrait_mode' => false,
            'blur_radius' => 20,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'thumbnail_url', 'preview_url']);
        File::delete($tempPath);
    }

    public function test_preview_bokeh_returns_base64_image(): void
    {
        $admin = $this->createAdmin();
        $tempPath = tempnam(sys_get_temp_dir(), 'ctrl_test_').'.jpg';
        $im = imagecreatetruecolor(50, 50);
        $white = imagecolorallocate($im, 255, 255, 255);
        if ($white !== false) {
            imagefill($im, 0, 0, $white);
        }

        imagejpeg($im, $tempPath);

        $fakeBytes = (string) file_get_contents($tempPath);
        Http::fake([
            '*/portrait-blur' => Http::response($fakeBytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $source = MediaSource::factory()->create();
        $media = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'file_path' => $tempPath,
            'file_hash' => hash_file('sha256', $tempPath),
            'mime_type' => 'image/jpeg',
            'width' => 50,
            'height' => 50,
            'file_size' => filesize($tempPath),
        ]);

        $response = $this->actingAs($admin, 'moonshine')->postJson("/admin/media/{$media->id}/preview-bokeh", [
            'blur_radius' => 20,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['preview_data_url']);
        File::delete($tempPath);
    }
}
