<?php

declare(strict_types=1);

use App\Enums\TagType;
use App\Models\MediaTag;
use Database\Seeders\MediaTagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('media tag seeder creates base english tags with is_public false', function (): void {
    $this->seed(MediaTagSeeder::class);

    $count = MediaTag::where('type', TagType::AiDetected)->count();
    expect($count)->toBeGreaterThanOrEqual(100);

    $allPrivate = MediaTag::where('type', TagType::AiDetected)->where('is_public', true)->count();
    expect($allPrivate)->toBe(0);

    // Common tags exist
    expect(MediaTag::where('name', 'landscape')->exists())->toBeTrue()
        ->and(MediaTag::where('name', 'portrait')->exists())->toBeTrue()
        ->and(MediaTag::where('name', 'sunset')->exists())->toBeTrue();
});
