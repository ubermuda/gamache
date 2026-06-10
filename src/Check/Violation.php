<?php

declare(strict_types=1);

namespace Gamache\Check;

final readonly class Violation
{
    public function __construct(
        public string $message,
        public Severity $severity,
        public string $file,
        public ?int $line = null,
    ) {
    }
}
