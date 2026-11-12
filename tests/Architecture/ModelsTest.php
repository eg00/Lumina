<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

uses()->group('architecture');

it('models are classes with strict types', function (): void {
    expect('App\Models')
        ->toBeClasses()
        ->ignoring('App\Models\Traits');
});

it('models extend base model', function (): void {
    expect('App\Models')
        ->toExtend(Model::class)
        ->ignoring('App\Models\Traits');
});

it('traits are properly used', function (): void {
    expect('App\Models\Traits')
        ->toBeTraits()
        ->toHavePrefix('Has')
        ->toOnlyBeUsedIn('App\Models');
});
