<?php

declare(strict_types=1);

use App\Models\MediaTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can toggle tag is_public attribute', function (): void {
    $tag = MediaTag::factory()->hidden()->create();

    $tag->update(['is_public' => true]);

    $tag->refresh();

    expect($tag->is_public)->toBeTrue();
});
