<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\CompactToVariablesRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Set\LaravelLevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // Paths to analyze
    $rectorConfig->paths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/resources',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ]);

    // Skip specific rules
    $rectorConfig->skip([
        CompactToVariablesRector::class,
        // Adding declare(strict_types=1) across the whole tree is a deliberate,
        // separate decision — keep this pass focused on real refactors + type fixes.
        SafeDeclareStrictTypesRector::class,
    ]);

    // Enable caching for Rector
    $rectorConfig->cacheDirectory(__DIR__.'/storage/rector/cache');

    // Apply sets for Laravel and general code quality
    $rectorConfig->sets([
        LaravelLevelSetList::UP_TO_LARAVEL_130_WITHOUT_ATTRIBUTES,
        SetList::CODE_QUALITY,
    ]);

    // Define PHP version for Rector
    $rectorConfig->phpVersion(PhpVersion::PHP_84);
};
