<?php

declare(strict_types=1);

namespace Gamache\Check;

final readonly class DesignSystemPattern
{
    /**
     * @param string  $pattern     a full PCRE pattern with delimiters and flags, matched against the whole template
     * @param string  $description what a match is, such as "one-button form", used in the message
     * @param ?string $ownTemplate path relative to the project root of the template that renders the component
     */
    public function __construct(
        public string $component,
        public string $pattern,
        public string $description,
        public ?string $ownTemplate = null,
        public bool $enforced = false,
    ) {
    }
}
