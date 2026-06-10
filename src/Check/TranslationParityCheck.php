<?php

declare(strict_types=1);

namespace Gamache\Check;

final class TranslationParityCheck extends AbstractCheck
{
    /** @var array<string, list<string>> locale => list of translation keys */
    private array $keysByLocale = [];
    /** @var array<string, string> locale => absolute file path */
    private array $absPathByLocale = [];

    public function getName(): string
    {
        return 'TranslationParityCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['translations/messages.*.xlf'];
    }

    public function run(string $absPath): void
    {
        if (!preg_match('/messages\.(.+)\.xlf$/', basename($absPath), $m)) {
            return;
        }
        $locale = $m[1];

        $xml = @simplexml_load_file($absPath);
        if (false === $xml) {
            return;
        }

        $keys = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $keys[] = (string) $unit['id'];
        }

        $this->keysByLocale[$locale] = $keys;
        $this->absPathByLocale[$locale] = $absPath;
    }

    #[\Override]
    public function getResult(): CheckResult
    {
        if (count($this->keysByLocale) < 2) {
            return new CheckResult($this->getName());
        }

        $violations = [];
        $allKeys = array_unique(array_merge(...array_values($this->keysByLocale)));

        foreach ($this->keysByLocale as $locale => $keys) {
            $keySet = array_flip($keys);
            foreach ($allKeys as $key) {
                if (isset($keySet[$key])) {
                    continue;
                }
                // Find the key in another locale's file to report file+line
                $sourceFile = null;
                $sourceLine = 1;
                foreach ($this->absPathByLocale as $sourceLocale => $filePath) {
                    if ($sourceLocale === $locale) {
                        continue;
                    }
                    $lines = file($filePath, FILE_IGNORE_NEW_LINES) ?: [];
                    foreach ($lines as $lineIndex => $lineContent) {
                        if (preg_match('/trans-unit id="'.preg_quote($key, '/').'"/', $lineContent)) {
                            $sourceFile = $filePath;
                            $sourceLine = $lineIndex + 1;
                            break 2;
                        }
                    }
                }

                if (null === $sourceFile) {
                    continue;
                }

                $violations[] = new Violation(
                    sprintf("Key '%s' is missing from locale '%s'", $key, $locale),
                    Severity::Error,
                    $sourceFile,
                    $sourceLine,
                );
            }
        }

        return new CheckResult($this->getName(), $violations);
    }
}
