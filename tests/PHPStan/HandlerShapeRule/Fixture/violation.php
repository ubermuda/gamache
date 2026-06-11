<?php

declare(strict_types=1);

namespace App\Module\Foo\Command;

class NotReadonlyHandler
{
    public function __invoke(): void
    {
    }

    public function helper(): void
    {
    }
}
