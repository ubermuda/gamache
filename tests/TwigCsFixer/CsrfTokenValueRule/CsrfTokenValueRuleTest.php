<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\CsrfTokenValueRule;

use Gamache\TwigCsFixer\CsrfTokenValueRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class CsrfTokenValueRuleTest extends AbstractRuleTestCase
{
    public function test_twig_expression_value_passes(): void
    {
        $this->checkRule(
            new CsrfTokenValueRule(),
            [],
            __DIR__.'/Fixture/valid.twig',
            false,
        );
    }

    public function test_literal_string_value_is_flagged(): void
    {
        $this->checkRule(
            new CsrfTokenValueRule(),
            ['CsrfTokenValue.Error:1:1' => 'CSRF token input value must be a Twig expression: value="{{ csrf_token(\'...\') }}".'],
            __DIR__.'/Fixture/violation.twig',
            false,
        );
    }
}
