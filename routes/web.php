<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\GeoAdminController;
use App\Http\Controllers\Admin\MediaEditorController;
use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GeoMapController;
use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GalleryController::class, 'index'])->name('home');
Route::get('/view/{mediaFile:uuid}', [GalleryController::class, 'view'])
    ->whereUuid('mediaFile')
    ->name('gallery.view');
Route::get('/search', [AiSearchController::class, 'search'])->name('gallery.search');
Route::get('/media/{mediaFile:uuid}/similar', [AiSearchController::class, 'similar'])
    ->whereUuid('mediaFile')
    ->name('gallery.similar');
Route::get('/media/{mediaFile:uuid}/stream', [MediaStreamController::class, 'stream'])
    ->whereUuid('mediaFile')
    ->name('media.stream');
Route::get('/map', [GeoMapController::class, 'index'])->name('gallery.map');
Route::get('/api/geo/markers', [GeoMapController::class, 'markers'])->name('api.geo.markers');

Route::middleware(['web', 'auth:moonshine'])->prefix('admin/media/{mediaFile:id}')->group(function (): void {
    Route::post('apply-edit', [MediaEditorController::class, 'applyEdit'])->name('admin.media.apply-edit');
    Route::post('preview-bokeh', [MediaEditorController::class, 'previewBokeh'])->name('admin.media.preview-bokeh');
});

Route::middleware(['web', 'auth:moonshine'])->prefix('admin/geo')->group(function (): void {
    Route::get('reverse', [GeoAdminController::class, 'reverse'])->name('admin.geo.reverse');
    Route::get('search', [GeoAdminController::class, 'search'])->name('admin.geo.search');
    Route::get('latest-coordinates', [GeoAdminController::class, 'latestCoordinates'])->name('admin.geo.latest');
});
