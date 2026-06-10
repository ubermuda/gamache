<?php

declare(strict_types=1);

namespace Gamache\Check;

final readonly class CheckResult
{
    /** @param list<Violation> $violations */
    public function __construct(
        public string $name,
        public array $violations = [],
        public bool $skipped = false,
    ) {
    }

    public static function skipped(string $name): self
    {
        return new self($name, [], skipped: true);
    }

    public function hasFailed(): bool
    {
        return array_any(
            $this->violations,
            fn (Violation $v) => Severity::Error === $v->severity,
        );
    }
}
