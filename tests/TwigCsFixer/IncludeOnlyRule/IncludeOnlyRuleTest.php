<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\IncludeOnlyRule;

use Gamache\TwigCsFixer\IncludeOnlyRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class IncludeOnlyRuleTest extends AbstractRuleTestCase
{
    public function test_include_with_only_passes(): void
    {
        $this->checkRule(
            new IncludeOnlyRule(),
            [],
            __DIR__.'/Fixture/valid.twig',
            false,
        );
    }

    public function test_include_without_only_is_flagged(): void
    {
        $this->checkRule(
            new IncludeOnlyRule(),
            ['IncludeOnly.Error:1:4' => '{% include %} must use "only" to prevent variable leakage into the included template.'],
            __DIR__.'/Fixture/violation.twig',
            false,
        );
    }
}
