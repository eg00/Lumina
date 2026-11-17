<?php

declare(strict_types=1);

use App\Enums\TagType;
use App\Models\MediaTag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('media tag has is_public cast and public scope', function (): void {
    $publicTag = MediaTag::factory()->create([
        'name' => 'Public Landscape',
        'is_public' => true,
    ]);

    $privateTag = MediaTag::factory()->create([
        'name' => 'Hidden Animal',
        'is_public' => false,
    ]);

    expect($publicTag->is_public)->toBeTrue()
        ->and($privateTag->is_public)->toBeFalse();

    $publicTags = MediaTag::public()->pluck('id');
    expect($publicTags)->toContain($publicTag->id)
        ->not->toContain($privateTag->id);
});

test('existing tags are backfilled as public during migration', function (): void {
    $tag = MediaTag::factory()->create([
        'name' => 'Existing User Tag',
        'type' => TagType::User,
    ]);

    $tag->refresh();

    expect($tag->is_public)->toBeTrue();
});
