<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * Skill files tell an agent which commands to run and which files to open, and
 * nothing links them to the things they name. Rename a justfile recipe or move
 * a directory and every skill still reads as authoritative — the next session
 * follows an instruction that cannot work, and the failure surfaces as the
 * agent improvising rather than as a broken build.
 *
 * Only fenced blocks and inline code spans are read, because a skill is prose
 * about a codebase: "just a moment" is English, `just cs` is a command. What
 * counts as a reference is deliberately narrow — a `just <recipe>` in command
 * position, and a path token under one of the configured prefixes — since a
 * check that cries wolf on a placeholder gets switched off.
 */
final class SkillReferenceCheck extends AbstractCheck
{
    /** @var list<string> */
    private const array DEFAULT_PATTERNS = ['.claude/skills/*/SKILL.md'];

    /**
     * `src/` and `tests/` are deliberately absent. Skills illustrate naming
     * conventions with paths that were never meant to resolve — a module called
     * X importing from a module called Y, a `CreateIssueHandler.php` that only
     * shows where a handler goes. Pointed at a real project's skills, every
     * finding under `src/` was one of those and none was rot. Add the prefix if
     * your skills cite only files that exist.
     *
     * @var list<string>
     */
    private const array DEFAULT_PATH_PREFIXES = [
        'assets/',
        'bin/',
        'config/',
        'docs/',
        'e2e/',
        'migrations/',
        'public/',
        'templates/',
        'translations/',
    ];

    /**
     * Characters that make a token a shape rather than a path: placeholders,
     * globs, variables and interpolation. Their presence is why the reference
     * cannot be resolved, so it is not reported.
     */
    private const string UNRESOLVABLE = '<>{}$%*?|"\'`';

    /** @var list<string> */
    private readonly array $patterns;

    /** @var list<string> */
    private readonly array $pathPrefixes;

    /**
     * @param list<string>|null $patterns     Skill files to scan. Defaults to `.claude/skills/*&#47;SKILL.md`.
     * @param string            $justfilePath Recipe source, relative to the project root. When the
     *                                        file is absent, recipe references are left alone
     *                                        rather than all reported as missing.
     * @param list<string>|null $pathPrefixes Only tokens starting with one of these are read as a
     *                                        file reference. Everything else in a code span is
     *                                        prose, a flag or somebody else's path.
     * @param list<string>      $ignoredRecipes Recipe names a skill may name although the justfile
     *                                          does not define them, e.g. one a plugin supplies.
     * @param list<string>      $ignoredPaths   Paths a skill may name although they are absent,
     *                                          e.g. one the project generates or gitignores.
     * @param Severity          $severity       Error by default: a skill naming a command that does
     *                                          not exist is wrong now, not stylistically off.
     */
    public function __construct(
        ?array $patterns = null,
        private readonly string $justfilePath = 'justfile',
        ?array $pathPrefixes = null,
        private readonly array $ignoredRecipes = [],
        private readonly array $ignoredPaths = [],
        private readonly Severity $severity = Severity::Error,
    ) {
        $this->patterns = $patterns ?? self::DEFAULT_PATTERNS;
        $this->pathPrefixes = $pathPrefixes ?? self::DEFAULT_PATH_PREFIXES;
    }

    public function getName(): string
    {
        return 'SkillReferenceCheck';
    }

    public function getTargetPatterns(): array
    {
        return $this->patterns;
    }

    public function run(string $absPath): void
    {
        $root = $this->projectRoot($absPath);
        if (null === $root) {
            return;
        }

        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $recipes = $this->recipes($root);

        foreach (self::codeSpans($content) as [$line, $span]) {
            $this->inspectSpan($span, $line, $absPath, $root, $recipes);
        }
    }

    /**
     * @param array<string, true>|null $recipes null when the project has no justfile
     */
    private function inspectSpan(string $span, int $line, string $absPath, string $root, ?array $recipes): void
    {
        $tokens = preg_split('/\s+/', trim($span)) ?: [];
        $previous = null;
        $expectRecipe = false;

        foreach ($tokens as $token) {
            if ($expectRecipe && null !== $recipes) {
                $this->checkRecipe($token, $line, $absPath, $recipes);
            }

            $expectRecipe = 'just' === $token && self::isCommandPosition($previous);

            if (!$expectRecipe) {
                $this->checkPath($token, $line, $absPath, $root);
            }

            $previous = $token;
        }
    }

    /** @param array<string, true> $recipes */
    private function checkRecipe(string $token, int $line, string $absPath, array $recipes): void
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]*$/', $token)) {
            return;
        }

        if (isset($recipes[$token]) || \in_array($token, $this->ignoredRecipes, true)) {
            return;
        }

        $this->violations[] = new Violation(
            sprintf(
                'References `just %s`, which %s does not define. Rename the reference or restore the recipe.',
                $token,
                $this->justfilePath,
            ),
            $this->severity,
            $absPath,
            $line,
        );
    }

    private function checkPath(string $token, int $line, string $absPath, string $root): void
    {
        $path = self::trimPunctuation($token);

        if (!self::hasPrefix($path, $this->pathPrefixes)) {
            return;
        }

        if (strcspn($path, self::UNRESOLVABLE) !== \strlen($path)) {
            return;
        }

        if (file_exists($root.'/'.$path) || \in_array($path, $this->ignoredPaths, true)) {
            return;
        }

        $this->violations[] = new Violation(
            sprintf('References `%s`, which does not exist. Update the path or restore the file.', $path),
            $this->severity,
            $absPath,
            $line,
        );
    }

    /**
     * Recipe names, aliases included. Assignments (`x := y`) and settings
     * (`set positional-arguments`) share the left margin with recipes and are
     * not recipes; a recipe line ends in `:` possibly followed by parameters
     * and dependencies.
     *
     * @return array<string, true>|null
     */
    private function recipes(string $root): ?array
    {
        $content = @file_get_contents($root.'/'.$this->justfilePath);
        if (false === $content) {
            return null;
        }

        $recipes = [];

        foreach (explode("\n", $content) as $rawLine) {
            if (1 === preg_match('/^alias\s+([a-zA-Z0-9_-]+)\s*:=/', $rawLine, $alias)) {
                $recipes[$alias[1]] = true;
                continue;
            }

            // A recipe body is indented, so only the left margin declares one.
            if (1 === preg_match('/^(\s|#|$)/', $rawLine)) {
                continue;
            }

            if (1 === preg_match('/^(set|import|export|mod|use)\b/', $rawLine)) {
                continue;
            }

            // `name := value` is an assignment; `name params:` is a recipe.
            if (1 === preg_match('/^[a-zA-Z0-9_-]+\s*:=/', $rawLine)) {
                continue;
            }

            if (1 === preg_match('/^@?([a-zA-Z0-9_-]+)[^:]*:/', $rawLine, $recipe)) {
                $recipes[$recipe[1]] = true;
            }
        }

        return $recipes;
    }

    /**
     * Code spans with the 1-based line they start on: whole lines inside a
     * fence, and the contents of each inline span outside one.
     *
     * @return list<array{int, string}>
     */
    private static function codeSpans(string $content): array
    {
        $spans = [];
        $fence = null;

        foreach (explode("\n", $content) as $index => $rawLine) {
            $line = $index + 1;
            $trimmed = ltrim($rawLine);

            if (1 === preg_match('/^(`{3,}|~{3,})/', $trimmed, $marker)) {
                if (null === $fence) {
                    $fence = $marker[1][0];
                } elseif (str_starts_with($marker[1], $fence)) {
                    $fence = null;
                }

                continue;
            }

            if (null !== $fence) {
                $spans[] = [$line, $rawLine];
                continue;
            }

            preg_match_all('/`([^`\n]+)`/', $rawLine, $matches);
            foreach ($matches[1] as $inline) {
                $spans[] = [$line, $inline];
            }
        }

        return $spans;
    }

    /**
     * `just` names a recipe when it opens a command. Anywhere else it is the
     * English adverb, which is how a skill says "just the failing spec".
     */
    private static function isCommandPosition(?string $previous): bool
    {
        return null === $previous || \in_array($previous, ['&&', '||', ';', '|', '&', '(', '$('], true);
    }

    private static function trimPunctuation(string $token): string
    {
        $trimmed = rtrim($token, ',.;:)]');

        return preg_replace('/:\d+$/', '', $trimmed) ?? $trimmed;
    }

    /** @param list<string> $prefixes */
    private static function hasPrefix(string $path, array $prefixes): bool
    {
        return array_any($prefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix));
    }

    private function projectRoot(string $absPath): ?string
    {
        foreach ($this->patterns as $pattern) {
            $position = strpos($pattern, '*');
            $static = false === $position ? $pattern : substr($pattern, 0, $position);
            $needle = '/'.ltrim($static, '/');

            $found = strrpos($absPath, $needle);
            if (false !== $found) {
                return substr($absPath, 0, $found);
            }
        }

        return null;
    }
}
