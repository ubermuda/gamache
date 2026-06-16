<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer;

use Gamache\TwigCsFixer\CsrfTokenValueRule;
use Gamache\TwigCsFixer\GamacheStandard;
use Gamache\TwigCsFixer\IncludeOnlyRule;
use Gamache\TwigCsFixer\InlineSvgRule;
use Gamache\TwigCsFixer\TranslationKeyRule;
use PHPUnit\Framework\TestCase;

final class GamacheStandardTest extends TestCase
{
    public function test_standard_provides_all_gamache_twig_rules(): void
    {
        $rules = (new GamacheStandard())->getRules();

        $classes = array_map(static fn (object $r): string => $r::class, $rules);

        self::assertEqualsCanonicalizing([
            CsrfTokenValueRule::class,
            IncludeOnlyRule::class,
            InlineSvgRule::class,
            TranslationKeyRule::class,
        ], $classes);
    }
}
