<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\InlineSvgRule;

use Gamache\TwigCsFixer\InlineSvgRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class InlineSvgRuleTest extends AbstractRuleTestCase
{
    public function test_ux_icon_passes(): void
    {
        $this->checkRule(
            new InlineSvgRule(),
            [],
            __DIR__.'/Fixture/valid.twig',
            false,
        );
    }

    public function test_inline_svg_is_flagged(): void
    {
        $this->checkRule(
            new InlineSvgRule(),
            ['InlineSvg.Error:1:1' => 'Inline <svg> elements are not allowed. Use &lt;twig:UX:Icon name="lucide:..." /&gt; instead.'],
            __DIR__.'/Fixture/violation.twig',
            false,
        );
    }
}
