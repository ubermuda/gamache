<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

final readonly class RenameSeriesHandler
{
    public function __invoke(string $series): string
    {
        return $series;
    }
}
