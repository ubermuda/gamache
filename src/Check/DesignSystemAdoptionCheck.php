<?php

declare(strict_types=1);

namespace Gamache\Check;

final class DesignSystemAdoptionCheck extends AbstractCheck
{
    private const string CLASS_LIST_PATTERN = <<<'REGEX'
        /(?<![\w:-])class\s*=\s*(["'])(?<attribute>(?:\{\{.*?\}\}|\{%.*?%\}|(?!\1).)*?)\1
          | (?<![\w-])(?:class|'class'|"class")\s*:\s*(?<hash>(?:'[^']*'|"[^"]*"|[^,}'"\n])+)
        /xs
        REGEX;

    private const string TWIG_COMMENT_PATTERN = '/\{#.*?#\}/s';

    /**
     * @param list<DesignSystemComponent> $components
     * @param list<string>                $ignoredPaths Folder or file paths to skip, matched as whole path segments
     */
    public function __construct(
        private readonly array $components = [],
        private readonly array $ignoredPaths = [],
    ) {
    }

    public function getName(): string
    {
        return 'DesignSystemAdoptionCheck';
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

        $components = array_filter(
            $this->components,
            static fn (DesignSystemComponent $component): bool => null === $component->ownTemplate
                || !str_ends_with($absPath, '/'.$component->ownTemplate),
        );
        if ([] === $components) {
            return;
        }

        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $content = (string) preg_replace_callback(
            self::TWIG_COMMENT_PATTERN,
            static fn (array $comment): string => (string) preg_replace('/[^\n]/', ' ', $comment[0]),
            $content,
        );

        if (false === preg_match_all(self::CLASS_LIST_PATTERN, $content, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE)) {
            $this->violations[] = new Violation(
                'Could not scan the class lists of this template: '.preg_last_error_msg(), // @translation-check-ignore
                Severity::Error,
                $absPath,
            );

            return;
        }

        $line = 1;
        $offset = 0;
        foreach ($matches as $match) {
            $line += substr_count($content, "\n", $offset, $match[0][1] - $offset);
            $offset = $match[0][1];
            $classList = '' !== ($match['attribute'][0] ?? '') ? $match['attribute'][0] : ($match['hash'][0] ?? '');

            foreach ($components as $component) {
                $wholeClass = '/(?<![\w-])'.preg_quote($component->rootClass, '/').'(?![\w-])/';
                if (1 !== preg_match($wholeClass, $classList)) {
                    continue;
                }
                $this->violations[] = new Violation(
                    \sprintf('Hand-written %s markup; render the %s component instead', $component->rootClass, $component->name), // @translation-check-ignore
                    $component->enforced ? Severity::Error : Severity::Warning,
                    $absPath,
                    $line,
                );
            }
        }
    }
}
