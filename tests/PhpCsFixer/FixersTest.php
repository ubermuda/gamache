<?php

declare(strict_types=1);

namespace Gamache\Tests\PhpCsFixer;

use Gamache\PhpCsFixer\Fixers;
use PhpCsFixer\Fixer\FixerInterface;
use PHPUnit\Framework\TestCase;

final class FixersTest extends TestCase
{
    public function test_collection_yields_custom_fixers(): void
    {
        $fixers = iterator_to_array(new Fixers());

        self::assertNotEmpty($fixers);
        foreach ($fixers as $fixer) {
            self::assertInstanceOf(FixerInterface::class, $fixer);
        }
    }

    public function test_rules_include_ordered_attributes(): void
    {
        self::assertSame(true, Fixers::rules()['ordered_attributes'] ?? null);
    }

    public function test_rules_match_shared_project_defaults(): void
    {
        $rules = Fixers::rules();

        $multilineAttribute = $rules['Gamache/multiline_attribute'] ?? null;
        self::assertIsArray($multilineAttribute);
        self::assertSame(3, $multilineAttribute['minimum_arguments']);
        self::assertSame(true, $rules['multiline_promoted_properties'] ?? null);
        self::assertSame(['case' => 'snake_case'], $rules['php_unit_method_casing'] ?? null);
    }

    public function test_every_custom_rule_resolves_to_a_registered_fixer(): void
    {
        $names = array_map(
            static fn (FixerInterface $f): string => $f->getName(),
            iterator_to_array(new Fixers()),
        );

        foreach (array_keys(Fixers::rules()) as $rule) {
            if (!str_starts_with($rule, 'Gamache/')) {
                continue;
            }
            self::assertContains($rule, $names, sprintf('Rule "%s" has no registered fixer', $rule));
        }
    }
}
