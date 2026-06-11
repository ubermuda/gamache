<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractFixableRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

final class IncludeOnlyRule extends AbstractFixableRule
{
    private const array WHITESPACE = [Token::WHITESPACE_TYPE, Token::TAB_TYPE, Token::EOL_TYPE];

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);

        if (!$token->isMatching(Token::BLOCK_NAME_TYPE, 'include')) {
            return;
        }

        // Scan forward skipping whitespace, looking for 'only' before %}
        $current = $tokenIndex + 1;
        while (false !== ($next = $tokens->findNext(self::WHITESPACE, $current, null, true))) {
            $t = $tokens->get($next);
            if ($t->isMatching(Token::BLOCK_END_TYPE)) {
                break; // reached %} without 'only'
            }
            if ($t->isMatching(Token::NAME_TYPE, 'only')) {
                return; // found 'only' — rule satisfied
            }
            $current = $next + 1;
        }

        $this->addError(
            '{% include %} must use "only" to prevent variable leakage into the included template.',
            $token,
        );
    }
}
