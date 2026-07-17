<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractFixableRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

/**
 * Template paths under the module template root must be referenced through
 * their Twig namespace: `@Event/show.html.twig`, not `Module/Event/show.html.twig`.
 *
 * Checks string literals in {% extends %}, {% include %}, {% embed %},
 * {% from %}, {% import %}, and {% use %} tags. The match is conservative —
 * `Module/<PascalCase>/...` — so templates in repos without that layout never
 * match.
 */
/*
 * Extends AbstractFixableRule (despite reporting non-fixable errors): under
 * twig-cs-fixer 4.x, non-fixable rules are dropped from the ruleset unless
 * the consumer opts in via Config::allowNonFixableRules(), which would also
 * unleash the Symfony standard's non-fixable rules.
 */
final class ModuleTemplateNamespaceRule extends AbstractFixableRule
{
    private const array TEMPLATE_TAGS = ['extends', 'include', 'embed', 'from', 'import', 'use'];

    /** String literal token (quotes included) matching the module template layout. */
    private const string MODULE_PATH_PATTERN = '#^([\'"])Module/([A-Z][A-Za-z0-9]*)/(.+)\1$#';

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);

        if (!$token->isMatching(Token::BLOCK_NAME_TYPE, self::TEMPLATE_TAGS)) {
            return;
        }

        $blockEnd = $tokens->findNext(Token::BLOCK_END_TYPE, $tokenIndex + 1);
        if (false === $blockEnd) {
            return;
        }

        for ($index = $tokenIndex + 1; $index < $blockEnd; ++$index) {
            $current = $tokens->get($index);
            if (!$current->isMatching(Token::STRING_TYPE)) {
                continue;
            }

            if (1 === preg_match(self::MODULE_PATH_PATTERN, $current->getValue(), $matches)) {
                $this->addError(sprintf(
                    'Template "Module/%s/%s" must be referenced through its Twig namespace: "@%s/%s".',
                    $matches[2],
                    $matches[3],
                    $matches[2],
                    $matches[3],
                ), $current);
            }
        }
    }
}
