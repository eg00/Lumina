<?php

declare(strict_types=1);

uses()->group('architecture');

it('interfaces must be in "App\Interfaces" namespace', function (): void {
    expect('App\Interfaces')
        ->toBeInterfaces();
});
