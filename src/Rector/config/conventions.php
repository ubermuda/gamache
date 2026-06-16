<?php

declare(strict_types=1);

use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;
use Rector\Php84\Rector\Class_\PropertyHookRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(InjectRepositoryInsteadOfGetRepositoryRector::class);
    $rectorConfig->rule(SortCallLikeNamedArgsRector::class);
    $rectorConfig->rule(SortAttributeNamedArgsRector::class);
    $rectorConfig->rule(PropertyHookRector::class);
};
