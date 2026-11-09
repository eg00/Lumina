<?php

declare(strict_types=1);

it('app should use strict types', function (): void {
    expect('App')
        ->toUseStrictTypes()
        ->not()->toUse(['die', 'dd', 'dump']);
});

it('http layer is properly isolated', function (): void {
    expect(['App\Http'])
        ->toOnlyBeUsedIn(['App\Http', 'App\Providers', 'App\Modules']);
});

it('follows security best practices', function (): void {
    expect(['App'])
        ->not()->toUse(['md5', 'eval']);
});

it('dtos are readonly', function (): void {
    expect('App\DTOs')
        ->toBeReadonly();
});

it('enums are actual enums', function (): void {
    expect('App\Enums')
        ->toBeEnums();
});

it('domain layer does not depend on http layer', function (): void {
    expect(['App\Models', 'App\DTOs', 'App\Enums'])
        ->not()->toUse('App\Http');
});

it('app should not use dangerous functions', function (): void {
    expect('App')
        ->not()->toUse(['exec', 'shell_exec', 'system', 'passthru', 'unserialize']);
});
