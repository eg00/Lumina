<?php

declare(strict_types=1);

use App\Models\MediaFile;
use App\Models\MediaSource;
use App\Models\Setting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use MoonShine\Laravel\Models\MoonshineUser;

uses(RefreshDatabase::class);

test('guest is redirected to moonshine admin login', function (): void {
    test()->get('/admin')->assertRedirect('/admin/login');
});

test('admin can access dashboard when authenticated', function (): void {
    $admin = MoonshineUser::factory()->create();

    test()->actingAs($admin, 'moonshine');

    test()->get('/admin')->assertSuccessful();
});

test('deleting media in moonshine marks file as ignored tombstone', function (): void {
    $admin = MoonshineUser::factory()->create();
    $file = MediaFile::factory()->create(['is_ignored' => false]);

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $response = test()->delete(route('moonshine.crud.destroy', [
        'resourceUri' => 'media-resource',
        'resourceItem' => $file->id,
    ]));

    $response->assertSessionHasNoErrors();

    $response->assertSessionHasNoErrors();

    $file->refresh();

    expect($file->is_ignored)->toBeTrue()
        ->and($file->deleted_at)->not->toBeNull();
});

test('admin can trigger scan on media source', function (): void {
    $tempDir = sys_get_temp_dir().'/test_scan_'.uniqid('', true);
    mkdir($tempDir);
    $filePath = $tempDir.'/photo.jpg';
    copy(base_path('tests/fixtures/sample_with_gps.jpg'), $filePath);

    $admin = MoonshineUser::factory()->create();
    $source = MediaSource::factory()->create(['path' => $tempDir, 'is_active' => true]);

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $url = '/admin/method/media-source-index-page/media-source-resource?method=scan&resourceItem='.$source->id;
    $response = test()->get($url);

    $response->assertSuccessful();

    test()->assertDatabaseHas('media_files', [
        'media_source_id' => $source->id,
        'file_path' => $filePath,
    ]);

    File::delete($filePath);
    @rmdir($tempDir);
});

test('admin can view and update feature settings in moonshine', function (): void {
    $admin = MoonshineUser::factory()->create();

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $response = test()->get('/admin/page/feature-settings-page');
    $response->assertSuccessful();

    $saveResponse = test()->post('/admin/method/feature-settings-page?method=save', [
        'ai_search' => '1',
        'geo_map' => '0',
        'tags' => '1',
        'default_sort' => 'captured_at_asc',
    ]);

    $saveResponse->assertSuccessful();

    expect(Setting::get('features.ai_search'))->toBeTrue()
        ->and(Setting::get('features.geo_map'))->toBeFalse()
        ->and(Setting::get('features.tags'))->toBeTrue()
        ->and(Setting::get('features.default_sort'))->toBe('captured_at_asc');
});

test('admin can view media form and detail pages without type errors', function (): void {
    $admin = MoonshineUser::factory()->create();
    $file = MediaFile::factory()->create();

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $editResponse = test()->get("/admin/resource/media-resource/media-form-page/{$file->id}");
    $editResponse->assertSuccessful();

    $detailResponse = test()->get("/admin/resource/media-resource/media-detail-page/{$file->id}");
    $detailResponse->assertSuccessful();
});

test('admin can create media tag with auto-generated slug', function (): void {
    $admin = MoonshineUser::factory()->create();

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $response = test()->post('/admin/resource/media-tag-resource/crud', [
        'name' => 'Mountain Trip',
        'type' => 'user',
    ]);

    $response->assertSessionHasNoErrors();

    test()->assertDatabaseHas('media_tags', [
        'name' => 'Mountain Trip',
        'slug' => 'mountain-trip',
        'type' => 'user',
    ]);
});

test('admin can toggle favorite and ignored via update-field in moonshine', function (): void {
    $admin = MoonshineUser::factory()->create();
    $file = MediaFile::factory()->create([
        'is_favorite' => false,
        'is_ignored' => false,
    ]);

    test()->withoutMiddleware(PreventRequestForgery::class)
        ->actingAs($admin, 'moonshine');

    $response = test()->put("/admin/update-field/column/media-resource/{$file->id}", [
        'field' => 'is_favorite',
        'value' => '1',
    ]);

    $response->assertSuccessful();
    expect($file->fresh()->is_favorite)->toBeTrue();

    $response2 = test()->put("/admin/update-field/column/media-resource/{$file->id}", [
        'field' => 'is_ignored',
        'value' => '1',
    ]);

    $response2->assertSuccessful();
    expect($file->fresh()->is_ignored)->toBeTrue();
});
