<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\TransPlaceholderEscapeRule;

use Gamache\TwigCsFixer\TransPlaceholderEscapeRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class TransPlaceholderEscapeRuleTest extends AbstractRuleTestCase
{
    public function test_escaped_literal_and_out_of_scope_placeholders_pass(): void
    {
        $this->checkRule(
            new TransPlaceholderEscapeRule(),
            [],
            __DIR__.'/Fixture/valid.html.twig',
            false,
        );
    }

    public function test_unescaped_tag_placeholders_and_double_escaped_filter_args_are_flagged(): void
    {
        $this->checkRule(
            new TransPlaceholderEscapeRule(),
            [
                'TransPlaceholderEscape.Error:1:26' => 'Placeholder %name% in a {% trans with %} tag must be escaped with |e — the trans tag bypasses autoescaping.',
                'TransPlaceholderEscape.Error:2:41' => 'Placeholder %bad% in a {% trans with %} tag must be escaped with |e — the trans tag bypasses autoescaping.',
                'TransPlaceholderEscape.Error:3:42' => 'Placeholder passed to the |trans filter must not use |e — the filter output is autoescaped, so this double-escapes.',
            ],
            __DIR__.'/Fixture/violation.html.twig',
            false,
        );
    }

    public function test_plain_text_templates_are_skipped(): void
    {
        $this->checkRule(
            new TransPlaceholderEscapeRule(),
            [],
            __DIR__.'/Fixture/skipped.txt.twig',
            false,
        );
    }
}
