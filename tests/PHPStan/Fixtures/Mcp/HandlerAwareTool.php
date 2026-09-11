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
