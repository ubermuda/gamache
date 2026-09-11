<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** A base class that injects no handler, so a subclass is on its own. */
abstract class PresenterAwareTool
{
    public function __construct(
        protected readonly DocumentPresenter $presenter,
    ) {
    }
}
