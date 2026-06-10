<?php

declare(strict_types=1);

namespace Gamache\Check;

final class TranslationCheck extends AbstractCheck
{
    /**
     * @param list<string|\Closure> $ignoredCallSites
     */
    public function __construct(
        private readonly int $threshold = 3,
        private readonly array $ignoredCallSites = [],
        private readonly bool $ignoreExceptionClasses = true,
    ) {
    }

    public function getName(): string
    {
        return 'TranslationCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['src/**/*.php', 'templates/**/*.twig'];
    }

    public function run(string $absPath): void
    {
        $this->ensureInitialized();

        $ext = pathinfo($absPath, PATHINFO_EXTENSION);

        if ('php' === $ext) {
            $this->scanPhpFile($absPath);
        } elseif ('twig' === $ext) {
            $this->scanTwigFile($absPath);
        }
    }

    // ── Lazy initialisation ────────────────────────────────────────────────────

    /** @var array<string, true>|null */
    private ?array $ignoredConstructors = null;
    /** @var array<string, true>|null */
    private ?array $ignoredMethods = null;

    private function ensureInitialized(): void
    {
        if (null !== $this->ignoredConstructors) {
            return;
        }

        $this->ignoredConstructors = [];
        $this->ignoredMethods = [];

        foreach ($this->ignoredCallSites as $entry) {
            if ($entry instanceof \Closure) {
                $this->ignoredMethods[new \ReflectionFunction($entry)->getName()] = true;
            } elseif (str_contains($entry, '::')) {
                [, $method] = explode('::', $entry, 2);
                $this->ignoredMethods[$method] = true;
            } else {
                $this->ignoredConstructors[ltrim($entry, '\\')] = true;
            }
        }
    }

    // ── Per-file scanning ──────────────────────────────────────────────────────

    private function scanPhpFile(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $tokens = token_get_all($content);
        $lines = explode("\n", $content);
        $importMap = $this->buildImportMap($tokens);

        foreach ($tokens as $tokenIndex => $token) {
            if (!is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
                continue;
            }
            [, $raw, $line] = $token;
            $sourceLine = $lines[$line - 1] ?? '';

            if (str_contains($sourceLine, '@translation-check-ignore')) {
                continue;
            }
            if ($this->isIgnoredCallSite($tokens, $tokenIndex, $importMap, $this->ignoredConstructors ?? [], $this->ignoredMethods ?? [])) {
                continue;
            }

            $value = trim($raw, "'\"");

            if (preg_match('/^[a-z][a-z0-9]*([._-][a-z0-9]+)*$/', $value)) {
                continue;
            }
            if (preg_match('/%[bcdeEfFgGhHosuxX]/', $value)) {
                continue;
            }

            $score = $this->translationScore($value);
            if ($score >= $this->threshold) {
                $this->violations[] = new Violation(
                    sprintf('Score %d  \'%s\'', $score, $value),
                    Severity::Warning,
                    $absPath,
                    $line,
                );
            }
        }
    }

    private function scanTwigFile(string $absPath): void
    {
        $lines = file($absPath, FILE_IGNORE_NEW_LINES) ?: [];

        foreach ($lines as $lineNo => $line) {
            if (str_contains($line, 'translation-check-ignore')) {
                continue;
            }
            if (!preg_match_all('/\{\{(.*?)\}\}/', $line, $exprMatches, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($exprMatches[0] as $ei => $exprEntry) {
                $exprOffset = $exprEntry[1];
                $exprContent = $exprMatches[1][$ei][0];

                if ($this->isInNonProseHtmlAttribute($line, $exprOffset)) {
                    continue;
                }

                /** @var list<array{value: string, start: int, end: int}> $strings */
                $strings = [];
                if (preg_match_all("/'([^']+)'/", $exprContent, $sq, PREG_OFFSET_CAPTURE)) {
                    foreach ($sq[0] as $si => [$raw, $off]) {
                        $strings[] = ['value' => $sq[1][$si][0], 'start' => $off, 'end' => $off + strlen($raw)];
                    }
                }
                if (preg_match_all('/"([^"]+)"/', $exprContent, $dq, PREG_OFFSET_CAPTURE)) {
                    foreach ($dq[0] as $si => [$raw, $off]) {
                        $strings[] = ['value' => $dq[1][$si][0], 'start' => $off, 'end' => $off + strlen($raw)];
                    }
                }
                foreach ($strings as ['value' => $value, 'start' => $start, 'end' => $end]) {
                    if ($this->isTwigHashKey($exprContent, $end)) {
                        continue;
                    }
                    if ($this->isTwigNonProseHashValue($exprContent, $start)) {
                        continue;
                    }
                    $score = $this->translationScore($value);
                    if ($score >= $this->threshold) {
                        $this->violations[] = new Violation(
                            sprintf('Score %d  \'%s\'', $score, $value),
                            Severity::Warning,
                            $absPath,
                            $lineNo + 1,
                        );
                    }
                }
            }
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array<string, string>
     */
    private function buildImportMap(array $tokens): array
    {
        $map = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; ++$i) {
            if (!is_array($tokens[$i]) || T_USE !== $tokens[$i][0]) {
                continue;
            }
            $fqcn = '';
            $alias = '';
            $inAlias = false;

            for ($j = $i + 1; $j < $count; ++$j) {
                $t = $tokens[$j];
                if (!is_array($t)) {
                    if (';' === $t || '{' === $t) {
                        break;
                    }
                    continue;
                }
                switch ($t[0]) {
                    case T_WHITESPACE:
                        break;
                    case T_AS:
                        $inAlias = true;
                        break;
                    case T_FUNCTION:
                    case T_CONST:
                        break;
                    case T_NAME_QUALIFIED:
                    case T_NAME_FULLY_QUALIFIED:
                    case T_STRING:
                        if ($inAlias) {
                            $alias = $t[1];
                        } else {
                            $fqcn = ltrim($t[1], '\\');
                        }
                        break;
                }
            }
            if ('' !== $fqcn) {
                $short = '' !== $alias ? $alias : basename(str_replace('\\', '/', $fqcn));
                $map[$short] = $fqcn;
            }
        }

        return $map;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     * @param array<string, string>                $importMap
     * @param array<string, true>                  $ignoredConstructors
     * @param array<string, true>                  $ignoredMethods
     */
    private function isIgnoredCallSite(
        array $tokens,
        int $index,
        array $importMap,
        array $ignoredConstructors,
        array $ignoredMethods,
    ): bool {
        $depth = 0;

        for ($i = $index - 1; $i >= 0; --$i) {
            $token = $tokens[$i];
            $value = is_array($token) ? $token[1] : $token;

            if (')' === $value) {
                ++$depth;
            } elseif ('(' === $value) {
                if ($depth > 0) {
                    --$depth;
                    continue;
                }
                for ($j = $i - 1; $j >= 0; --$j) {
                    $prev = $tokens[$j];
                    if (is_array($prev) && T_WHITESPACE === $prev[0]) {
                        continue;
                    }
                    if (!is_array($prev)) {
                        break;
                    }
                    $tokenValue = $prev[1];
                    $baseName = basename(str_replace('\\', '/', ltrim($tokenValue, '\\')));

                    if ('__construct' === $baseName) {
                        for ($k = $j - 1; $k >= 0; --$k) {
                            $kTok = $tokens[$k];
                            if (is_array($kTok) && T_WHITESPACE === $kTok[0]) {
                                continue;
                            }
                            if (is_array($kTok) && T_DOUBLE_COLON === $kTok[0]) {
                                continue;
                            }
                            if (!is_array($kTok)) {
                                break;
                            }
                            $classBase = basename(str_replace('\\', '/', ltrim($kTok[1], '\\')));
                            if (in_array($classBase, ['parent', 'self', 'static'], true)) {
                                return true;
                            }
                            if ($this->ignoreExceptionClasses && str_ends_with($classBase, 'Exception')) {
                                return true;
                            }
                            break;
                        }
                        break;
                    }

                    if (isset($ignoredMethods[$baseName])) {
                        return true;
                    }

                    $fqcn = $importMap[$baseName]
                        ?? (str_contains($tokenValue, '\\') ? ltrim($tokenValue, '\\') : null);

                    if (null !== $fqcn && isset($ignoredConstructors[$fqcn])) {
                        return true;
                    }

                    if ($this->ignoreExceptionClasses && str_ends_with($baseName, 'Exception')) {
                        return true;
                    }

                    break;
                }

                return false;
            }
        }

        return false;
    }

    private function isInNonProseHtmlAttribute(string $line, int $offset): bool
    {
        $before = substr($line, 0, $offset);

        return (bool) preg_match(
            '/(?:class|id|href|src|name|type|style|rel|target|method|action|enctype|for|data-[\w-]+)\s*=\s*["\'"][^"\']*$/',
            $before,
        );
    }

    private function isTwigHashKey(string $exprContent, int $afterQuote): bool
    {
        return str_starts_with(ltrim(substr($exprContent, $afterQuote)), ':');
    }

    private function isTwigNonProseHashValue(string $exprContent, int $stringStart): bool
    {
        $before = substr($exprContent, 0, $stringStart);

        return (bool) preg_match(
            '/(?:\'|")?(?:class|id|href|src|name|type|style|rel|target|method|action|enctype|for|data-[\w-]+)(?:\'|")?\s*:\s*$/',
            $before,
        );
    }

    private function translationScore(string $value): int
    {
        $score = 0;

        if (str_contains($value, ' ')) {
            $score += 3;
        }
        if (preg_match('/^[A-Z]/', $value)) {
            ++$score;
        }
        if (preg_match('/[.?!:]$/', $value)) {
            ++$score;
        }
        if (mb_strlen($value) > 15) {
            ++$score;
        }

        $functionWords = ['the', 'your', 'you', 'has', 'is', 'are', 'to', 'a', 'an', 'we', 'my', 'our'];
        $lower = strtolower($value);
        foreach ($functionWords as $word) {
            if (str_contains($lower, ' '.$word.' ') || str_starts_with($lower, $word.' ')) {
                ++$score;
                break;
            }
        }

        if (preg_match('/^[a-z]+$/', $value)) {
            $score -= 2;
        }
        if (preg_match('/^[^a-zA-Z]/', $value)) {
            $score -= 2;
        }
        if (
            preg_match('/^[a-z0-9:_\/.\\[\\]-]+(?: [a-z0-9:_\/.\\[\\]-]+)*$/', $value)
            && preg_match('/[-:\[\/]/', $value)
        ) {
            $score -= 2;
        }

        return $score;
    }
}
