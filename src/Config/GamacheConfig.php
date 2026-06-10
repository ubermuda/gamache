<?php

declare(strict_types=1);

namespace Gamache\Config;

use Gamache\Check\CheckInterface;

final class GamacheConfig
{
    /** @var list<CheckInterface> */
    public private(set) array $checks = [];

    /** @param list<CheckInterface> $checks */
    public function registerChecks(array $checks): self
    {
        $this->checks = $checks;

        return $this;
    }

    public static function fromFile(string $projectRoot): self
    {
        $path = $projectRoot.'/gamache.php';
        if (!file_exists($path)) {
            return new self();
        }

        $config = require $path;
        if (!$config instanceof self) {
            return new self();
        }

        return $config;
    }
}
