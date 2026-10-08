<?php

declare(strict_types=1);

namespace Gamache\Check;

final readonly class DesignSystemComponent
{
    /** @param ?string $ownTemplate path relative to the project root of the template that renders the component */
    public function __construct(
        public string $name,
        public string $rootClass,
        public ?string $ownTemplate = null,
        public bool $enforced = false,
    ) {
    }
}
