<?php

declare(strict_types=1);

use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([InjectRepositoryInsteadOfGetRepositoryRector::class])
    ->withImportNames();
