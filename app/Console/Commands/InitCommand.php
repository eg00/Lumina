<?php

namespace App\Console\Commands;

use App\Models\MediaSource;
use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;

#[Signature('init')]
#[Description('Initialize database with default admin user, settings, and media source')]
class InitCommand extends Command
{
    public function handle(): int
    {
        $this->info('Starting Lumina initialization...');

        $this->initAdmin();
        $this->initSettings();
        $this->initMediaSource();

        $this->info('Lumina initialization completed successfully.');

        return self::SUCCESS;
    }

    private function initAdmin(): void
    {
        $email = config('lumina.admin.email');
        $password = config('lumina.admin.password');
        $name = config('lumina.admin.name', 'Admin');

        if (empty($email) || empty($password)) {
            $this->warn('ADMIN_EMAIL or ADMIN_PASSWORD not configured. Skipping admin creation.');

            return;
        }

        $user = MoonshineUser::query()->where('email', (string) $email)->first();

        if ($user !== null) {
            $this->info("MoonShine admin user [{$email}] already exists. Skipping.");

            return;
        }

        MoonshineUser::query()->create([
            'name' => (string) $name,
            'email' => (string) $email,
            'password' => Hash::make((string) $password),
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);

        $this->info("Created MoonShine admin user: [{$email}]");
    }

    private function initSettings(): void
    {
        /** @var array<string, mixed> $defaults */
        $defaults = [
            'features.ai_search' => true,
            'features.geo_map' => true,
            'features.tags' => true,
            'features.default_sort' => 'captured_at_desc',
        ];

        foreach ($defaults as $key => $defaultValue) {
            if (! Setting::query()->where('key', $key)->exists()) {
                Setting::set($key, $defaultValue);
                $this->info("Initialized setting: [{$key}]");
            }
        }
    }

    private function initMediaSource(): void
    {
        $path = config('lumina.initial_media.path');
        $name = config('lumina.initial_media.name', 'Photos');

        if (empty($path)) {
            return;
        }

        $source = MediaSource::query()->firstOrCreate(
            ['path' => (string) $path],
            ['name' => (string) $name, 'is_active' => true]
        );

        $this->info("Ensured initial media source: [{$source->name}] ({$source->path})");
    }
}
