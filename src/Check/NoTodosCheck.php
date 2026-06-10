<?php

declare(strict_types=1);

namespace Gamache\Check;

final class NoTodosCheck extends AbstractCheck
{
    public function getName(): string
    {
        return 'NoTodosCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['src/**/*.php'];
    }

    public function run(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'TODO')
                || str_contains($line, 'FIXME')
                || str_contains($line, 'XXX')
                || str_contains($line, '@todo')
            ) {
                $this->violations[] = new Violation(
                    'TODO/FIXME/XXX comment found; move follow-up work to a tracking file', // @translation-check-ignore
                    Severity::Error,
                    $absPath,
                    $index + 1,
                );
            }
        }
    }
}
