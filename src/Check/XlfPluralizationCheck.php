<?php

declare(strict_types=1);

namespace Gamache\Check;

final class XlfPluralizationCheck extends AbstractCheck
{
    private const string XLIFF_NS = 'urn:oasis:names:tc:xliff:document:1.2';

    public function getName(): string
    {
        return 'XlfPluralizationCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['translations/messages.*.xlf'];
    }

    public function run(string $absPath): void
    {
        $xml = @simplexml_load_file($absPath);
        if (false === $xml) {
            return;
        }

        $xml->registerXPathNamespace('x', self::XLIFF_NS);

        /** @var \SimpleXMLElement[] $units */
        $units = $xml->xpath('//x:trans-unit') ?: [];

        foreach ($units as $unit) {
            $children = $unit->children(self::XLIFF_NS);
            $target = (string) ($children->target ?? '');

            // Only check pipe-separated plural strings
            if (!str_contains($target, '|')) {
                continue;
            }

            // Check for a zero-count case
            if (str_contains($target, '{0}') || str_contains($target, '=0')) {
                continue;
            }

            $id = (string) ($unit->attributes()['id'] ?? '');

            $this->violations[] = new Violation(
                sprintf( // @translation-check-ignore
                    'Translation key "%s" has plural form but is missing a {0} or =0 (zero-count) case.',
                    $id,
                ),
                Severity::Error,
                $absPath,
                0,
            );
        }
    }

    #[\Override]
    public function getResult(): CheckResult
    {
        return new CheckResult($this->getName(), $this->violations);
    }
}
