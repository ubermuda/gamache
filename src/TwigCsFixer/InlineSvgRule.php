<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractFixableRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

final class InlineSvgRule extends AbstractFixableRule
{
    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);

        if (!$token->isMatching(Token::TEXT_TYPE)) {
            return;
        }

        if (!str_contains($token->getValue(), '<svg')) {
            return;
        }

        $this->addError(
            'Inline <svg> elements are not allowed. Use &lt;twig:UX:Icon name="lucide:..." /&gt; instead.',
            $token,
        );
    }
}
