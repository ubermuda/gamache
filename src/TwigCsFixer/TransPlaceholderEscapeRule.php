<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

/**
 * The {% trans with %} *tag* bypasses Twig autoescaping, so non-literal
 * placeholder values must be escaped with |e. The |trans *filter* is the
 * opposite: its output is autoescaped, so |e on a placeholder double-escapes.
 *
 * Check 1: a non-literal placeholder value inside a `{% trans with {...} %}`
 * hash without an |e / |escape filter is flagged.
 *
 * Check 2: an |e / |escape filter inside a `|trans({...})` argument is flagged,
 * but only when the expression is rendered directly in an autoescaped
 * `{{ ... }}` output — assignments and |raw pipelines keep their pre-escaping.
 *
 * Both checks only apply to *.html.twig templates: plain-text templates
 * (emails, chat messages) have autoescaping off, and |e there would corrupt
 * the output with HTML entities.
 *
 * False positives (e.g. a placeholder that is guaranteed HTML-safe) can be
 * suppressed with {# twig-cs-fixer-disable-next-line TransPlaceholderEscape #}.
 */
final class TransPlaceholderEscapeRule extends AbstractRule
{
    private const array WHITESPACE = [Token::WHITESPACE_TYPE, Token::TAB_TYPE, Token::EOL_TYPE];
    private const array ESCAPE_FILTERS = ['e', 'escape'];
    private const array OPENERS = ['{', '(', '['];
    private const array CLOSERS = ['}', ')', ']'];

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        $token = $tokens->get($tokenIndex);

        if (!str_ends_with($token->getFilename(), '.html.twig')) {
            return;
        }

        if ($token->isMatching(Token::BLOCK_NAME_TYPE, 'trans')) {
            $this->checkTransTag($tokenIndex, $tokens);

            return;
        }

        if ($token->isMatching(Token::FILTER_NAME_TYPE, 'trans')) {
            $this->checkTransFilter($tokenIndex, $tokens);
        }
    }

    private function checkTransTag(int $tagIndex, Tokens $tokens): void
    {
        $blockEnd = $tokens->findNext(Token::BLOCK_END_TYPE, $tagIndex + 1);
        if (false === $blockEnd) {
            return;
        }

        $with = false;
        for ($index = $tagIndex + 1; $index < $blockEnd; ++$index) {
            if ($tokens->get($index)->isMatching(Token::NAME_TYPE, 'with')) {
                $with = $index;
                break;
            }
        }
        if (false === $with) {
            return;
        }

        $hashOpen = $tokens->findNext(self::WHITESPACE, $with + 1, null, true);
        if (false === $hashOpen || !$tokens->get($hashOpen)->isMatching(Token::PUNCTUATION_TYPE, '{')) {
            // `with vars` (a variable, not a hash literal) cannot be inspected statically.
            return;
        }

        $index = $hashOpen + 1;
        while ($index < $blockEnd) {
            $index = $this->checkPlaceholderItem($index, $blockEnd, $tokens);
            if (false === $index) {
                return;
            }
        }
    }

    /**
     * Checks one `key: value` item of the placeholder hash, starting at $index.
     * Returns the index just past the item's trailing `,`, or false when the
     * closing `}` (or the block end) was reached.
     */
    private function checkPlaceholderItem(int $index, int $blockEnd, Tokens $tokens): int|false
    {
        $index = $tokens->findNext(self::WHITESPACE, $index, null, true);
        if (false === $index || $index >= $blockEnd) {
            return false;
        }
        if ($tokens->get($index)->isMatching(Token::PUNCTUATION_TYPE, '}')) {
            return false;
        }

        $keyToken = $tokens->get($index);

        // Find the `:` separating key and value (keys are simple literals here).
        $colon = false;
        for (; $index < $blockEnd; ++$index) {
            if ($tokens->get($index)->isMatching(Token::PUNCTUATION_TYPE, ':')) {
                $colon = $index;
                break;
            }
            if ($tokens->get($index)->isMatching(Token::PUNCTUATION_TYPE, [',', '}'])) {
                // Malformed / spread item — skip past it.
                return $tokens->get($index)->isMatching(Token::PUNCTUATION_TYPE, ',') ? $index + 1 : false;
            }
        }
        if (false === $colon) {
            return false;
        }

        // Collect the value run: tokens until `,` or the hash's closing `}` at depth 0.
        $valueTokens = [];
        $firstValueIndex = null;
        $depth = 0;
        for ($index = $colon + 1; $index < $blockEnd; ++$index) {
            $current = $tokens->get($index);
            if (0 === $depth && $current->isMatching(Token::PUNCTUATION_TYPE, [',', '}'])) {
                break;
            }
            if ($current->isMatching(Token::PUNCTUATION_TYPE, self::OPENERS)) {
                ++$depth;
            } elseif ($current->isMatching(Token::PUNCTUATION_TYPE, self::CLOSERS)) {
                --$depth;
            }
            if ($current->isMatching(self::WHITESPACE)) {
                continue;
            }
            $valueTokens[] = $current;
            $firstValueIndex ??= $index;
        }

        if (null !== $firstValueIndex && !$this->valueIsSafe($valueTokens)) {
            $this->addError(sprintf(
                'Placeholder %s in a {%% trans with %%} tag must be escaped with |e — the trans tag bypasses autoescaping.',
                trim($keyToken->getValue(), '\'"'),
            ), $tokens->get($firstValueIndex));
        }

        $atEnd = $index >= $blockEnd || $tokens->get($index)->isMatching(Token::PUNCTUATION_TYPE, '}');

        return $atEnd ? false : $index + 1;
    }

    /** @param list<Token> $valueTokens */
    private function valueIsSafe(array $valueTokens): bool
    {
        if (1 === \count($valueTokens) && $valueTokens[0]->isMatching([Token::STRING_TYPE, Token::NUMBER_TYPE])) {
            return true;
        }

        foreach ($valueTokens as $token) {
            if ($token->isMatching(Token::FILTER_NAME_TYPE, self::ESCAPE_FILTERS)) {
                return true;
            }
        }

        return false;
    }

    private function checkTransFilter(int $filterIndex, Tokens $tokens): void
    {
        if (!$this->isDirectOutput($filterIndex, $tokens)) {
            return;
        }

        $argsOpen = $tokens->findNext(self::WHITESPACE, $filterIndex + 1, null, true);
        if (false === $argsOpen || !$tokens->get($argsOpen)->isMatching(Token::PUNCTUATION_TYPE, '(')) {
            return;
        }

        $argsClose = $this->findMatchingParen($argsOpen, $tokens);
        if (false === $argsClose) {
            return;
        }

        $escapeIndex = false;
        for ($index = $argsOpen + 1; $index < $argsClose; ++$index) {
            if ($tokens->get($index)->isMatching(Token::FILTER_NAME_TYPE, self::ESCAPE_FILTERS)) {
                $escapeIndex = $index;
                break;
            }
        }
        if (false === $escapeIndex) {
            return;
        }

        // A |raw further down the pipeline disables autoescaping for this
        // output — the placeholder pre-escaping is then the protection.
        $varEnd = $tokens->findNext(Token::VAR_END_TYPE, $argsClose + 1);
        $limit = false === $varEnd ? $argsClose : $varEnd;
        for ($index = $argsClose + 1; $index < $limit; ++$index) {
            if ($tokens->get($index)->isMatching(Token::FILTER_NAME_TYPE, 'raw')) {
                return;
            }
        }

        $this->addError(
            'Placeholder passed to the |trans filter must not use |e — the filter output is autoescaped, so this double-escapes.',
            $tokens->get($escapeIndex),
        );
    }

    /**
     * Whether the token at $index sits directly inside a `{{ ... }}` output
     * statement (as opposed to a {% set %} assignment or another tag).
     */
    private function isDirectOutput(int $index, Tokens $tokens): bool
    {
        for ($current = $index - 1; $current >= 0; --$current) {
            $token = $tokens->get($current);
            if ($token->isMatching(Token::VAR_START_TYPE)) {
                return true;
            }
            if ($token->isMatching([Token::VAR_END_TYPE, Token::BLOCK_START_TYPE, Token::BLOCK_END_TYPE])) {
                return false;
            }
        }

        return false;
    }

    private function findMatchingParen(int $openIndex, Tokens $tokens): int|false
    {
        $depth = 0;
        $index = $openIndex;
        while (true) {
            $token = $tokens->get($index);
            if ($token->isMatching(Token::PUNCTUATION_TYPE, '(')) {
                ++$depth;
            } elseif ($token->isMatching(Token::PUNCTUATION_TYPE, ')')) {
                --$depth;
                if (0 === $depth) {
                    return $index;
                }
            } elseif ($token->isMatching(Token::EOF_TYPE)) {
                return false;
            }
            ++$index;
        }
    }
}
