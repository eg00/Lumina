<?php

declare(strict_types=1);

use Illuminate\Console\Command;

it('command is strict type and extends command', function (): void {
    expect('App\Console\Commands')
        ->toBeClasses()
        ->toUseStrictTypes()
        ->toExtend(Command::class)
        ->toHaveMethod('handle');
});
