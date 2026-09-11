<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** A base class that injects the handler on a subclass's behalf. */
abstract class HandlerAwareTool
{
    public function __construct(
        protected readonly ArchiveDocumentHandler $archive,
    ) {
    }
}

/** A base class that injects no handler, so a subclass is on its own. */
abstract class PresenterAwareTool
{
    public function __construct(
        protected readonly DocumentPresenter $presenter,
    ) {
    }
}
