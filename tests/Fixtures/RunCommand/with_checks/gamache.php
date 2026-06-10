<?php

declare(strict_types=1);

use Gamache\Check\ServicesYamlCheck;
use Gamache\Config\GamacheConfig;

return new GamacheConfig()->registerChecks([
    new ServicesYamlCheck(),
]);
