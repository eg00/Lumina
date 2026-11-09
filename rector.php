<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\RemoveReadonlyPropertyVisibilityOnReadonlyClassRector;
use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Rector\MethodCall\RedirectRouteToToRouteHelperRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        CompactToVariablesRector::class,
        RemoveReadonlyPropertyVisibilityOnReadonlyClassRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        privatization: true,
        typeDeclarations: true,
        rectorPreset: true,
    )
    ->withSkip([
        CompactToVariablesRector::class,
        DispatchToHelperFunctionsRector::class,
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RemoveReadonlyPropertyVisibilityOnReadonlyClassRector::class,
        RedirectRouteToToRouteHelperRector::class,
    ])
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
    ])
    ->withAttributesSets(phpunit: true)
    ->withComposerBased(laravel: true, phpunit: true)
    ->withPhpSets(php85: true)
    ->withImportNames(importShortClasses: false)
    ->withCache(__DIR__.'/storage/rector');
