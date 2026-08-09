<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * Flags runs of consecutive comment lines longer than the configured budget.
 * A long comment is usually a decision log — the investigation, the
 * alternatives weighed, the benchmark numbers — which reads better in the
 * commit message or pull request that introduced it, leaving only the
 * constraint a reader needs at that line.
 *
 * Warning by default, so the run still exits 0. Pass `Severity::Error` to make
 * the budget binding: the `@comment-budget-ignore` marker is what carries the
 * judgment a line count cannot, so a project that wants every unsuppressed
 * block to be a deliberate one can enforce rather than advise.
 *
 * The comment syntax follows the file's extension; anything unrecognised is
 * read as `#`-commented, which covers YAML, dotenv, justfiles, Dockerfiles and
 * shell. Annotation carriers are exempt, since their length is driven by the
 * annotations rather than by prose: PHP docblocks and JSDoc `/**` blocks. A
 * blank line does not break a run — a comment split by one still reads as a
 * single block. Suppress a genuine false positive with `@comment-budget-ignore`
 * anywhere in the block.
 */
final class CommentBudgetCheck extends AbstractCheck
{
    /** @var list<string> */
    private const array DEFAULT_PATTERNS = [
        'src/**/*.php',
        'tests/**/*.php',
        'config/**/*.yaml',
        'templates/**/*.twig',
        'assets/**/*.js',
        'assets/**/*.css',
    ];

    /** @var list<string> */
    private readonly array $patterns;

    /** @param list<string> $patterns files to scan; defaults to the usual Symfony layout */
    public function __construct(
        private readonly int $maxLines = 5,
        ?array $patterns = null,
        private readonly Severity $severity = Severity::Warning,
    ) {
        $this->patterns = $patterns ?? self::DEFAULT_PATTERNS;
    }

    public function getName(): string
    {
        return 'CommentBudgetCheck';
    }

    public function getTargetPatterns(): array
    {
        return $this->patterns;
    }

    public function run(string $absPath): void
    {
        // The runner's `**` globbing does not prune dependency directories, so a
        // pattern like `e2e/**/*.ts` otherwise reports on shipped node_modules.
        if (str_contains($absPath, '/vendor/') || str_contains($absPath, '/node_modules/')) {
            return;
        }

        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $extension = strtolower(pathinfo($absPath, \PATHINFO_EXTENSION));

        if ('php' === $extension) {
            $this->scanPhp($content, $absPath);

            return;
        }

        [$linePrefix, $blockOpen, $blockClose, $blockExempt] = match ($extension) {
            'js', 'ts', 'mjs', 'jsx', 'tsx', 'css', 'scss' => ['//', '/*', '*/', '/**'],
            'twig' => [null, '{#', '#}', null],
            default => ['#', null, null, null],
        };

        $this->scanLines($content, $absPath, $linePrefix, $blockOpen, $blockClose, $blockExempt);
    }

    /**
     * PHP is tokenised rather than read line by line, so that `//` inside a
     * string literal is never mistaken for a comment.
     */
    private function scanPhp(string $content, string $absPath): void
    {
        $run = new CommentRun();

        foreach (token_get_all($content) as $token) {
            if (\is_array($token) && \T_WHITESPACE === $token[0]) {
                continue;
            }

            if (\is_array($token) && \T_COMMENT === $token[0] && str_starts_with(ltrim($token[1]), '//')) {
                $run->add($token[2], $token[1]);

                continue;
            }

            $this->flush($run, $absPath);
        }

        $this->flush($run, $absPath);
    }

    private function scanLines(
        string $content,
        string $absPath,
        ?string $linePrefix,
        ?string $blockOpen,
        ?string $blockClose,
        ?string $blockExempt,
    ): void {
        $run = new CommentRun();
        $inBlock = false;
        $inExemptBlock = false;

        foreach (explode("\n", $content) as $index => $line) {
            $trimmed = trim($line);
            $number = $index + 1;

            if ($inBlock) {
                if (!$inExemptBlock) {
                    $run->add($number, $line);
                }
                if (null !== $blockClose && str_contains($line, $blockClose)) {
                    $inBlock = false;
                    $inExemptBlock = false;
                }

                continue;
            }

            if ('' === $trimmed) {
                continue;
            }

            // A Symfony Flex section marker is structural, not prose. It is a
            // `#` line, so without this it glues the comments on either side of
            // it into one run and reports two in-budget blocks as one long one.
            if (str_starts_with($trimmed, '###>') || str_starts_with($trimmed, '###<')) {
                $this->flush($run, $absPath);

                continue;
            }

            if (1 === $number && str_starts_with($trimmed, '#!')) {
                continue;
            }

            if (null !== $blockOpen && str_starts_with($trimmed, $blockOpen)) {
                $inExemptBlock = null !== $blockExempt && str_starts_with($trimmed, $blockExempt);
                $inBlock = null === $blockClose || !str_contains(substr($trimmed, \strlen($blockOpen)), $blockClose);
                if (!$inExemptBlock) {
                    $run->add($number, $line);
                }

                continue;
            }

            if (null !== $linePrefix && str_starts_with($trimmed, $linePrefix)) {
                $run->add($number, $line);

                continue;
            }

            $this->flush($run, $absPath);
        }

        $this->flush($run, $absPath);
    }

    private function flush(CommentRun $run, string $absPath): void
    {
        $length = $run->length;
        $startLine = $run->startLine;
        $suppressed = $run->suppressed;
        $run->reset();

        if ($suppressed || $length <= $this->maxLines) {
            return;
        }

        $this->violations[] = new Violation(
            \sprintf(
                'Comment block of %d lines exceeds the %d-line budget; keep the constraint here and move the reasoning to the commit message', // @translation-check-ignore
                $length,
                $this->maxLines,
            ),
            $this->severity,
            $absPath,
            $startLine,
        );
    }
}
