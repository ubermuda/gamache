<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * A page title is composed in the template from two translated strings:
 * {{ 'x.page.title'|trans }} — {{ 'app.name'|trans }}. The page-title value
 * itself must therefore carry only the page name — never the brand, never the
 * separator. The brand is read from app.name's own target per locale, so this
 * works in any language without hard-coding the brand string.
 */
final class PageTitleBrandNameCheck extends AbstractCheck
{
    private const string XLIFF_NS = 'urn:oasis:names:tc:xliff:document:1.2';

    private const string SEPARATOR = ' — ';

    public function getName(): string
    {
        return 'PageTitleBrandNameCheck';
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

        $brand = $this->resolveBrand($units);

        foreach ($units as $unit) {
            $id = (string) ($unit->attributes()['id'] ?? '');
            if (!str_ends_with($id, '.page.title')) {
                continue;
            }

            $target = (string) ($unit->children(self::XLIFF_NS)->target ?? '');

            $containsBrand = null !== $brand && '' !== $brand && str_contains($target, $brand);
            $containsSeparator = str_contains($target, self::SEPARATOR);

            if (!$containsBrand && !$containsSeparator) {
                continue;
            }

            $this->violations[] = new Violation(
                sprintf( // @translation-check-ignore
                    'Page-title translation "%s" must contain only the page name. The brand and separator are composed in the template (\'x.page.title\'|trans ~ \' — \' ~ \'app.name\'|trans). Found: "%s".',
                    $id,
                    $target,
                ),
                Severity::Error,
                $absPath,
                0,
            );
        }
    }

    /**
     * @param \SimpleXMLElement[] $units
     */
    private function resolveBrand(array $units): ?string
    {
        foreach ($units as $unit) {
            if ('app.name' === (string) ($unit->attributes()['id'] ?? '')) {
                return trim((string) ($unit->children(self::XLIFF_NS)->target ?? ''));
            }
        }

        return null;
    }
}
