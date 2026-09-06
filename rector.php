<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\StringCastAssertStringContainsStringRector;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/tests'])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
    )
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
    ->withSkip([
        // Renames the service locator instead of removing it: see R-04.
        AppToResolveRector::class,
        // Writes a fully qualified name inside a closure signature, on one line.
        AddArrayFunctionClosureParamTypeRector::class,
        // Casts a `mixed` array value instead of typing the plan it comes from: see R-02.
        StringCastAssertStringContainsStringRector::class,
        // Reorders named arguments, which reads as a change where nothing changed.
        SortCallLikeNamedArgsRector::class,
    ])
    // Rector writes fully qualified names in inferred types; the codebase imports everything.
    ->withImportNames(importShortClasses: false)
    ->withCache(__DIR__.'/.rector');
