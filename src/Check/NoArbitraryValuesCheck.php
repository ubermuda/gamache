<?php

declare(strict_types=1);

namespace Gamache\Check;

final class NoArbitraryValuesCheck extends AbstractCheck
{
    /** @param list<string> $ignoredFiles Relative paths (from project root) to skip */
    public function __construct(
        private readonly array $ignoredFiles = [],
    ) {
    }

    public function getName(): string
    {
        return 'NoArbitraryValuesCheck';
    }

    public function getTargetPatterns(): array
    {
        return [
            'templates/**/*.twig',
            'assets/**/*.js',
            'assets/styles/app.css',
        ];
    }

    public function run(string $absPath): void
    {
        foreach ($this->ignoredFiles as $ignoredFile) {
            if (str_ends_with($absPath, '/'.$ignoredFile)) {
                return;
            }
        }

        $ext = pathinfo($absPath, PATHINFO_EXTENSION);

        if ('twig' === $ext || 'js' === $ext) {
            $pattern = '/[a-z]-\[|\[var\(/';
        } elseif ('css' === $ext) {
            $pattern = '/@apply[^;]*\[[0-9]/';
        } else {
            return;
        }

        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $index => $line) {
            if (str_contains($line, '@arbitrary-value-ignore')) {
                continue;
            }
            if (preg_match($pattern, $line)) {
                $this->violations[] = new Violation(
                    'Arbitrary Tailwind value found; use a semantic class or named Tailwind token instead', // @translation-check-ignore
                    Severity::Error,
                    $absPath,
                    $index + 1,
                );
            }
        }
    }
}
