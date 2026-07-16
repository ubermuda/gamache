<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Standard\StandardInterface;

/**
 * Aggregate of every gamache Twig-CS-Fixer rule. Reference via
 * `$ruleset->addStandard(new GamacheStandard())` so new gamache rules arrive
 * automatically on `composer update` without editing the config.
 */
final class GamacheStandard implements StandardInterface
{
    /**
     * @return list<\TwigCsFixer\Rules\RuleInterface|\TwigCsFixer\Rules\Node\NodeRuleInterface>
     */
    public function getRules(): array
    {
        return [
            new CsrfTokenValueRule(),
            new IncludeOnlyRule(),
            new InlineSvgRule(),
            new ModuleTemplateNamespaceRule(),
            new TranslationKeyRule(),
            new TransPlaceholderEscapeRule(),
        ];
    }
}
