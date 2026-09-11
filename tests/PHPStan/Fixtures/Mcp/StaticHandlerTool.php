<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Holds the handler on the class, not on the instance. */
abstract class StaticHandlerTool
{
    protected static ArchiveDocumentHandler $archive;
}
