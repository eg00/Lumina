<?php

declare(strict_types=1);

use App\Models\MediaSource;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use MoonShine\Laravel\Models\MoonshineUser;

uses(RefreshDatabase::class);

test('init creates admin user from configuration', function (): void {
    config([
        'lumina.admin.email' => 'admin@example.com',
        'lumina.admin.password' => 'my-secure-password',
        'lumina.admin.name' => 'Chief Admin',
    ]);

    /** @var PendingCommand $command */
    $command = $this->artisan('init');
    $command->assertSuccessful()->run();

    $user = MoonshineUser::query()->where('email', 'admin@example.com')->first();

    $this->assertNotNull($user);
    expect($user->name)->toBe('Chief Admin')
        ->and(Hash::check('my-secure-password', $user->password))->toBeTrue();
});

test('init does not overwrite existing admin password', function (): void {
    $existingUser = MoonshineUser::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('original-password'),
    ]);

    config([
        'lumina.admin.email' => 'existing@example.com',
        'lumina.admin.password' => 'new-different-password',
    ]);

    /** @var PendingCommand $command */
    $command = $this->artisan('init');
    $command->assertSuccessful()->run();

    $existingUser->refresh();

    expect(Hash::check('original-password', $existingUser->password))->toBeTrue();
});

test('init seeds default settings without overwriting existing ones', function (): void {
    Setting::set('features.ai_search', false);

    /** @var PendingCommand $command */
    $command = $this->artisan('init');
    $command->assertSuccessful()->run();

    expect(Setting::get('features.ai_search'))->toBeFalse()
        ->and(Setting::get('features.geo_map'))->toBeTrue()
        ->and(Setting::get('features.tags'))->toBeTrue()
        ->and(Setting::get('features.default_sort'))->toBe('captured_at_desc');
});

test('init creates initial media source when configured', function (): void {
    config([
        'lumina.initial_media.path' => '/media/library',
        'lumina.initial_media.name' => 'Family Archive',
    ]);

    /** @var PendingCommand $command */
    $command = $this->artisan('init');
    $command->assertSuccessful()->run();

    $source = MediaSource::query()->where('path', '/media/library')->first();

    $this->assertNotNull($source);
    expect($source->name)->toBe('Family Archive')
        ->and($source->is_active)->toBeTrue();
});
