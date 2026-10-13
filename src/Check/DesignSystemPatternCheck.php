<?php

declare(strict_types=1);

namespace Gamache\Check;

final class DesignSystemPatternCheck extends AbstractCheck
{
    private const string TWIG_COMMENT_PATTERN = '/\{#.*?#\}/s';

    /**
     * @param list<DesignSystemPattern> $patterns
     * @param list<string>              $ignoredPaths Folder or file paths to skip, matched as whole path segments
     */
    public function __construct(
        private readonly array $patterns = [],
        private readonly string $ignoreMarker = '@design-system-ignore',
        private readonly array $ignoredPaths = [],
    ) {
    }

    public function getName(): string
    {
        return 'DesignSystemPatternCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['templates/**/*.twig'];
    }

    public function run(string $absPath): void
    {
        foreach ($this->ignoredPaths as $ignoredPath) {
            $prefix = '/'.rtrim($ignoredPath, '/');
            if (str_contains($absPath, $prefix.'/') || str_ends_with($absPath, $prefix)) {
                return;
            }
        }

        $patterns = array_filter(
            $this->patterns,
            static fn (DesignSystemPattern $pattern): bool => null === $pattern->ownTemplate
                || !str_ends_with($absPath, '/'.$pattern->ownTemplate),
        );
        if ([] === $patterns) {
            return;
        }

        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $markedLines = [];
        if ('' !== $this->ignoreMarker) {
            foreach (explode("\n", $content) as $index => $text) {
                if (str_contains($text, $this->ignoreMarker)) {
                    $markedLines[$index + 1] = true;
                }
            }
        }

        $content = (string) preg_replace_callback(
            self::TWIG_COMMENT_PATTERN,
            static fn (array $comment): string => (string) preg_replace('/[^\n]/', ' ', $comment[0]),
            $content,
        );

        foreach ($patterns as $pattern) {
            if (false === @preg_match_all($pattern->pattern, $content, $matches, \PREG_OFFSET_CAPTURE)) {
                $this->violations[] = new Violation(
                    \sprintf('Could not match the %s pattern of the %s component: %s', $pattern->description, $pattern->component, preg_last_error_msg()), // @translation-check-ignore
                    Severity::Error,
                    $absPath,
                );

                continue;
            }

            foreach ($matches[0] as [$text, $offset]) {
                $startLine = substr_count($content, "\n", 0, $offset) + 1;
                $endLine = $startLine + substr_count($text, "\n");
                if ($this->isMarked($markedLines, $startLine, $endLine)) {
                    continue;
                }
                $this->violations[] = new Violation(
                    \sprintf('Hand-written %s; render the %s component instead', $pattern->description, $pattern->component), // @translation-check-ignore
                    $pattern->enforced ? Severity::Error : Severity::Warning,
                    $absPath,
                    $startLine,
                );
            }
        }
    }

    /** @param array<int, true> $markedLines */
    private function isMarked(array $markedLines, int $startLine, int $endLine): bool
    {
        for ($line = $startLine; $line <= $endLine; ++$line) {
            if (isset($markedLines[$line])) {
                return true;
            }
        }

        return false;
    }
}
