<?php

declare(strict_types=1);

use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GeoMapController;
use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GalleryController::class, 'index'])->name('home');
Route::get('/view/{mediaFile}', [GalleryController::class, 'view'])->name('gallery.view');
Route::get('/search', [AiSearchController::class, 'search'])->name('gallery.search');
Route::get('/media/{mediaFile}/similar', [AiSearchController::class, 'similar'])->name('gallery.similar');
Route::get('/media/{mediaFile}/stream', [MediaStreamController::class, 'stream'])->name('media.stream');
Route::get('/map', [GeoMapController::class, 'index'])->name('gallery.map');
Route::get('/api/geo/markers', [GeoMapController::class, 'markers'])->name('api.geo.markers');
