<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * Flags references to internal or ephemeral development artifacts inside CODE
 * COMMENTS (PHP line comments, block comments and docblocks; Twig comments):
 * numbered tasks ("Task 16"), a handoff/design document ("handoff screen 8"),
 * spec sections
 * ("§3.5", "spec §3.9"), dev phases ("Part 1", "Phase 1"), and dated decisions
 * ("owner decision …"). Such a comment is meaningless to a reader without the
 * referenced document open, so it should state the underlying fact directly.
 *
 * Only comment tokens are scanned — a match inside a string literal (log-event
 * name, translation key, test data) is data, not a comment, and is never
 * flagged. Suppress a genuine false positive (e.g. Apple's real "Handoff"
 * continuity feature) with `@comment-check-ignore` on the same line.
 */
final class SelfContainedCommentsCheck extends AbstractCheck
{
    private const string IGNORE_MARKER = '@comment-check-ignore';

    /** @var list<non-empty-string> */
    private const array PATTERNS = [
        '/\bTask \d+/i',
        '/\bhandoff\b/i',
        '/§\s*\d/',
        '/\bspec §/i',
        '/\bPhase \d+\b/i',
        '/\bPart \d+\b/i',
        '/\bplan header\b/i',
        '/\bowner decision\b/i',
        '/\bMANDATORY conventions\b/i',
        '/\bBLOCKER-grade\b/i',
    ];

    public function getName(): string
    {
        return 'SelfContainedCommentsCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['src/**/*.php', 'tests/**/*.php', 'templates/**/*.twig'];
    }

    public function run(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        if (str_ends_with($absPath, '.twig')) {
            $this->scanTwigComments($content, $absPath);

            return;
        }

        $this->scanPhpComments($content, $absPath);
    }

    private function scanPhpComments(string $content, string $absPath): void
    {
        foreach (token_get_all($content) as $token) {
            if (!\is_array($token)) {
                continue;
            }
            [$id, $text, $line] = $token;
            if (\T_COMMENT === $id || \T_DOC_COMMENT === $id) {
                $this->scanCommentText($text, $absPath, $line);
            }
        }
    }

    private function scanTwigComments(string $content, string $absPath): void
    {
        if (0 === preg_match_all('/\{#.*?#\}/s', $content, $matches, \PREG_OFFSET_CAPTURE)) {
            return;
        }

        foreach ($matches[0] as [$comment, $offset]) {
            $line = 1 + substr_count(substr($content, 0, $offset), "\n");
            $this->scanCommentText($comment, $absPath, $line);
        }
    }

    private function scanCommentText(string $text, string $absPath, int $startLine): void
    {
        // Scan each physical line so the ignore marker and the reported line
        // number are accurate inside a multi-line comment or docblock.
        foreach (explode("\n", $text) as $offset => $line) {
            if (str_contains($line, self::IGNORE_MARKER)) {
                continue;
            }
            foreach (self::PATTERNS as $pattern) {
                if (1 === preg_match($pattern, $line)) {
                    $this->violations[] = new Violation(
                        'Comment references an internal development document; make it self-contained', // @translation-check-ignore
                        Severity::Error,
                        $absPath,
                        $startLine + $offset,
                    );

                    break;
                }
            }
        }
    }
}
