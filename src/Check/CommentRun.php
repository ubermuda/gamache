<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * @internal mutable accumulator for a run of consecutive comment lines
 */
final class CommentRun
{
    public const string IGNORE_MARKER = '@comment-budget-ignore';

    public int $length = 0;
    public int $startLine = 0;
    public bool $suppressed = false;

    public function add(int $line, string $text): void
    {
        if (0 === $this->length) {
            $this->startLine = $line;
        }
        $this->suppressed = $this->suppressed || str_contains($text, self::IGNORE_MARKER);
        ++$this->length;
    }

    public function reset(): void
    {
        $this->length = 0;
        $this->startLine = 0;
        $this->suppressed = false;
    }
}
