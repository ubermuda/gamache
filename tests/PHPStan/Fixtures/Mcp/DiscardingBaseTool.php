<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Takes a handler and keeps nothing, so a subclass inherits nothing. */
abstract class DiscardingBaseTool
{
    public function __construct(ArchiveDocumentHandler $archive)
    {
    }
}
