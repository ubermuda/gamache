<?php

declare(strict_types=1);

namespace Gamache\Check;

abstract class AbstractCheck implements CheckInterface
{
    /** @var list<Violation> */
    protected array $violations = [];

    public function getResult(): CheckResult
    {
        return new CheckResult($this->getName(), $this->violations);
    }
}
