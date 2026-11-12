<?php

declare(strict_types=1);

use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GalleryController::class, 'index'])->name('home');
Route::get('/view/{mediaFile}', [GalleryController::class, 'view'])->name('gallery.view');
Route::get('/search', [AiSearchController::class, 'search'])->name('gallery.search');
Route::get('/media/{mediaFile}/similar', [AiSearchController::class, 'similar'])->name('gallery.similar');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/media/{mediaFile}/stream', [MediaStreamController::class, 'stream'])
    ->name('media.stream');
