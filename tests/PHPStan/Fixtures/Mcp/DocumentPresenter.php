<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\Fixtures\Mcp;

/** Not a handler, whatever a consumer chooses to import it as. */
final readonly class DocumentPresenter
{
    public function present(string $documentId): string
    {
        return $documentId;
    }
}
