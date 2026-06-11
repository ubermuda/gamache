<?php

declare(strict_types=1);

namespace App\Module\Foo\Command;

class BadCommand
{
    public function __construct(
        public string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
