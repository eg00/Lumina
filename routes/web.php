<?php

use App\Http\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

Route::get('/media/{mediaFile}/stream', [MediaStreamController::class, 'stream'])
    ->name('media.stream');
