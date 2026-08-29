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
     * Words that, immediately after a reference, mark it as being named rather
     * than run — "a `just merge-main` recipe" proposes one, "run `just cs`"
     * asserts one exists.
     *
     * @var list<string>
     */
    private const array DEFAULT_MENTION_MARKERS = ['recipe', 'recipes', 'style'];

    /** How much prose after a span is read looking for a marker. */
    private const int MENTION_LOOKAHEAD = 24;

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

    /** @var list<string> */
    private readonly array $mentionMarkers;

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
     *                                          e.g. one the project generates without gitignoring
     *                                          it. Gitignored paths are excused already.
     * @param Severity          $severity       Error by default: a skill naming a command that does
     *                                          not exist is wrong now, not stylistically off.
     * @param list<string>|null $mentionMarkers Words that, following a recipe reference, mark it as
     *                                          named rather than run. Pass an empty list to assert
     *                                          every reference, at the cost of reporting proposals.
     */
    public function __construct(
        ?array $patterns = null,
        private readonly string $justfilePath = 'justfile',
        ?array $pathPrefixes = null,
        private readonly array $ignoredRecipes = [],
        private readonly array $ignoredPaths = [],
        private readonly Severity $severity = Severity::Error,
        ?array $mentionMarkers = null,
    ) {
        $this->patterns = $patterns ?? self::DEFAULT_PATTERNS;
        $this->pathPrefixes = $pathPrefixes ?? self::DEFAULT_PATH_PREFIXES;
        $this->mentionMarkers = $mentionMarkers ?? self::DEFAULT_MENTION_MARKERS;
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

        /** @var list<array{Violation, string|null}> $candidates */
        $candidates = [];

        foreach (self::codeSpans($content) as [$line, $span, $after]) {
            $this->inspectSpan($span, $after, $line, $absPath, $root, $recipes, $candidates);
        }

        $this->record($root, $candidates);
    }

    /**
     * @param array<string, true>|null            $recipes    null when the project has no justfile
     * @param list<array{Violation, string|null}> $candidates
     */
    private function inspectSpan(string $span, string $after, int $line, string $absPath, string $root, ?array $recipes, array &$candidates): void
    {
        $tokens = preg_split('/\s+/', trim($span)) ?: [];
        $previous = null;
        $expectRecipe = false;

        foreach ($tokens as $token) {
            if ($expectRecipe && null !== $recipes && !$this->isMention($token, $span, $after)) {
                $violation = $this->recipeViolation($token, $line, $absPath, $recipes);
                if (null !== $violation) {
                    $candidates[] = [$violation, null];
                }
            }

            $expectRecipe = 'just' === $token && self::isCommandPosition($previous);

            if (!$expectRecipe) {
                $candidate = $this->pathViolation($token, $line, $absPath, $root);
                if (null !== $candidate) {
                    $candidates[] = $candidate;
                }
            }

            $previous = $token;
        }
    }

    /**
     * Keeps the violations git does not excuse, in the order they were found.
     * Paths reach git in one call rather than one per path, and only a file
     * with an unresolved reference in it makes that call at all.
     *
     * @param list<array{Violation, string|null}> $candidates
     */
    private function record(string $root, array $candidates): void
    {
        $paths = [];
        foreach ($candidates as [, $path]) {
            if (null !== $path) {
                $paths[$path] = true;
            }
        }

        $ignored = self::gitIgnored($root, array_keys($paths));

        foreach ($candidates as [$violation, $path]) {
            if (null !== $path && isset($ignored[$path])) {
                continue;
            }

            $this->violations[] = $violation;
        }
    }

    /** @param array<string, true> $recipes */
    private function recipeViolation(string $token, int $line, string $absPath, array $recipes): ?Violation
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]*$/', $token)) {
            return null;
        }

        if (isset($recipes[$token]) || \in_array($token, $this->ignoredRecipes, true)) {
            return null;
        }

        return new Violation(
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

    /** @return array{Violation, string}|null the path is carried alongside so git can excuse it */
    private function pathViolation(string $token, int $line, string $absPath, string $root): ?array
    {
        $path = self::trimPunctuation($token);

        if (!self::hasPrefix($path, $this->pathPrefixes)) {
            return null;
        }

        if (strcspn($path, self::UNRESOLVABLE) !== \strlen($path)) {
            return null;
        }

        if (file_exists($root.'/'.$path) || \in_array($path, $this->ignoredPaths, true)) {
            return null;
        }

        $violation = new Violation(
            sprintf('References `%s`, which does not exist. Update the path or restore the file.', $path),
            $this->severity,
            $absPath,
            $line,
        );

        return [$violation, $path];
    }

    /**
     * Which of these paths git ignores: a gitignored path is a generated
     * artifact, so its absence from a fresh checkout is expected rather than
     * rot. Each candidate is offered with and without a trailing slash, because
     * a directory-only pattern such as `node_modules/` does not match a path
     * git cannot see on disk to be a directory. Without an answer from git —
     * none installed, no repository — nothing is excused.
     *
     * @param list<string> $paths
     *
     * @return array<string, true>
     */
    private static function gitIgnored(string $root, array $paths): array
    {
        if ([] === $paths) {
            return [];
        }

        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $process = @proc_open(['git', 'check-ignore', '--stdin'], $descriptors, $pipes, $root);

        if (!\is_resource($process)) {
            return [];
        }

        $query = '';
        foreach ($paths as $path) {
            $query .= $path."\n".$path."/\n";
        }

        fwrite($pipes[0], $query);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($process);

        if (false === $output || !\in_array($status, [0, 1], true)) {
            return [];
        }

        $ignored = [];

        foreach (explode("\n", $output) as $reported) {
            $path = rtrim(trim($reported), '/');
            if ('' !== $path) {
                $ignored[$path] = true;
            }
        }

        return $ignored;
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
     * Code spans with the 1-based line they start on and the prose that follows
     * them: whole lines inside a fence, and the contents of each inline span
     * outside one. A fenced line is followed by nothing, because a fence is
     * code — every reference in it is invoked rather than named.
     *
     * @return list<array{int, string, string}>
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
                $spans[] = [$line, $rawLine, ''];
                continue;
            }

            preg_match_all('/`([^`\n]+)`/', $rawLine, $matches, \PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as $index => $inline) {
                [$whole, $offset] = $matches[0][$index];
                $spans[] = [$line, $inline[0], substr($rawLine, $offset + \strlen($whole), self::MENTION_LOOKAHEAD)];
            }
        }

        return $spans;
    }

    /**
     * A reference is a mention rather than a use when the recipe name ends the
     * code span and a marker word follows it: "a `just merge-main`-style
     * recipe" names a recipe that may not exist yet, which is a proposal rather
     * than a broken instruction. Anything the span goes on to say — arguments,
     * a pipeline — makes it a command again.
     */
    private function isMention(string $recipe, string $span, string $after): bool
    {
        if ([] === $this->mentionMarkers || !str_ends_with(rtrim($span), $recipe)) {
            return false;
        }

        $markers = implode('|', array_map(preg_quote(...), $this->mentionMarkers));

        return 1 === preg_match('/^[\s-]*(?:'.$markers.')\b/i', $after);
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
