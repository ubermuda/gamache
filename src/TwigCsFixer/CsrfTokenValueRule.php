<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

/**
 * Detects <input name="_csrf_token"> elements whose value attribute is a literal
 * string instead of a Twig expression.
 *
 * BAD:  <input type="hidden" name="_csrf_token" value="delete-project">
 * GOOD: <input type="hidden" name="_csrf_token" value="{{ csrf_token('delete-project') }}">
 *
 * SameOriginCsrfTokenManager::isTokenValid() rejects values shorter than 24
 * characters that are not the cookie sentinel "csrf-token". A literal token ID
 * always fails CSRF validation in production while tests pass (CSRF is disabled
 * in the test environment).
 */
final class CsrfTokenValueRule extends AbstractRule
{
    private const array WHITESPACE_TOKEN_TYPES = [
        Token::WHITESPACE_TYPE,
        Token::EOL_TYPE,
        Token::TAB_TYPE,
    ];

    /**
     * Pattern that matches a complete HTML tag text containing name="_csrf_token"
     * with a literal (non-Twig) value attribute.
     *
     * The value must NOT start with {{ — if it did, TwigCsFixer would have
     * emitted a VAR_START token rather than leaving the expression inside the
     * TEXT_TYPE token.  A plain string like value="delete-project" stays fully
     * inside the TEXT_TYPE and matches this pattern.
     */
    private const string PATTERN = '/name=["\']_csrf_token["\'][^>]*value=["\'][^{"\'<>][^"\'<>]*["\']/s';

    /**
     * The index of the first TEXT_TYPE token not yet covered by a reported run.
     * Reset at token index 0 to handle successive files.
     */
    private int $nextTokenToReport = 0;

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        if (0 === $tokenIndex) {
            $this->nextTokenToReport = 0;
        }

        $token = $tokens->get($tokenIndex);

        if (!$token->isMatching(Token::TEXT_TYPE)) {
            return;
        }

        if ($tokenIndex < $this->nextTokenToReport) {
            return;
        }

        // Collect consecutive TEXT_TYPE tokens separated only by whitespace into
        // one combined string so that multi-line input elements are handled
        // correctly.  This mirrors the approach in TranslationKeyRule.
        $combinedText = $token->getValue();
        $runEndIdx = $tokenIndex;
        $lookFrom = $tokenIndex + 1;

        while (true) {
            $nextNonWs = $tokens->findNext(self::WHITESPACE_TOKEN_TYPES, $lookFrom, null, true);
            if (false === $nextNonWs) {
                break;
            }
            $nextToken = $tokens->get($nextNonWs);
            if (!$nextToken->isMatching(Token::TEXT_TYPE)) {
                break;
            }
            for ($i = $runEndIdx + 1; $i < $nextNonWs; ++$i) {
                $combinedText .= $tokens->get($i)->getValue();
            }
            $combinedText .= $nextToken->getValue();
            $runEndIdx = $nextNonWs;
            $lookFrom = $nextNonWs + 1;
        }

        $this->nextTokenToReport = $runEndIdx + 1;

        // Split the combined text on '<' to get individual tag segments.
        // Each segment starting with a non-'/' character is an opening tag.
        // We reconstruct the full opening tag text (up to the next '>') and
        // match against it so that name= and value= from two different adjacent
        // inputs are never combined into a false positive.
        $parts = explode('<', $combinedText);
        foreach ($parts as $part) {
            if ('' === $part || '/' === $part[0]) {
                // closing tag or empty — skip
                continue;
            }

            $tagText = '<'.(string) strstr($part, '>', true).'>';
            if (!str_contains($tagText, '_csrf_token')) {
                continue;
            }

            // Check both orderings of name/value attributes.
            if (preg_match(self::PATTERN, $tagText)) {
                $this->addError(
                    'CSRF token input value must be a Twig expression: value="{{ csrf_token(\'...\') }}".',
                    $token,
                );

                return; // one error per token run is enough
            }

            // Also check value-before-name ordering.
            if (preg_match(
                '/value=["\'][^{"\'<>][^"\'<>]*["\'][^>]*name=["\']_csrf_token["\']/s',
                $tagText,
            )) {
                $this->addError(
                    'CSRF token input value must be a Twig expression: value="{{ csrf_token(\'...\') }}".',
                    $token,
                );

                return;
            }
        }
    }
}
