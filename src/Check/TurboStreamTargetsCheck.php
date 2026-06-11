<?php

declare(strict_types=1);

namespace Gamache\Check;

final class TurboStreamTargetsCheck extends AbstractCheck
{
    /** @var array<string, string> static id value → absolute file path where it was found */
    private array $definedIds = [];

    /** @var list<array{target: string, file: string, line: int}> */
    private array $streamTargets = [];

    public function getName(): string
    {
        return 'TurboStreamTargetsCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['templates/**/*.html.twig'];
    }

    public function run(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        // Collect static id="..." values (skip Twig expressions)
        preg_match_all('/\bid="([^"]+)"/', $content, $idMatches);
        foreach ($idMatches[1] as $id) {
            if (!str_contains($id, '{{') && !str_contains($id, '{%')) {
                $this->definedIds[$id] = $absPath;
            }
        }

        // Collect static target="..." from <turbo-stream> elements
        if (!str_contains($content, '<turbo-stream')) {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $lineIndex => $line) {
            preg_match_all('/<turbo-stream[^>]*\btarget="([^"]+)"/', $line, $targetMatches);
            foreach ($targetMatches[1] as $target) {
                if (!str_contains($target, '{{') && !str_contains($target, '{%')) {
                    $this->streamTargets[] = [
                        'target' => $target,
                        'file' => $absPath,
                        'line' => $lineIndex + 1,
                    ];
                }
            }
        }
    }

    #[\Override]
    public function getResult(): CheckResult
    {
        foreach ($this->streamTargets as $entry) {
            if (!isset($this->definedIds[$entry['target']])) {
                $this->violations[] = new Violation(
                    sprintf(
                        'Turbo stream target="%s" has no matching id="%s" in any template.', // @translation-check-ignore
                        $entry['target'],
                        $entry['target'],
                    ),
                    Severity::Error,
                    $entry['file'],
                    $entry['line'],
                );
            }
        }

        return new CheckResult($this->getName(), $this->violations);
    }
}
