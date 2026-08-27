<?php

declare(strict_types=1);

namespace Mcp\Capability\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class McpTool
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
