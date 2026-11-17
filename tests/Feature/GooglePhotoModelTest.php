<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\MediaSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GooglePhotoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_file_can_persist_and_query_google_photo_id(): void
    {
        $source = MediaSource::factory()->create();

        $file = MediaFile::factory()->create([
            'media_source_id' => $source->id,
            'google_photo_id' => 'gphoto_test_id_12345',
        ]);

        $this->assertDatabaseHas('media_files', [
            'id' => $file->id,
            'google_photo_id' => 'gphoto_test_id_12345',
        ]);

        $found = MediaFile::query()->where('google_photo_id', 'gphoto_test_id_12345')->first();
        $this->assertNotNull($found);
        $this->assertSame($file->id, $found->id);
    }
}
