<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Rules\AbstractFixableRule;
use TwigCsFixer\Token\Token;
use TwigCsFixer\Token\Tokens;

final class TranslationKeyRule extends AbstractFixableRule
{
    private const string KEY_PATTERN = '/^[a-z][a-z0-9]*([._-][a-z0-9]+)*$/';

    private const array WHITESPACE_TOKEN_TYPES = [
        Token::WHITESPACE_TYPE,
        Token::EOL_TYPE,
        Token::TAB_TYPE,
    ];

    /**
     * Tracks whether the tokenizer is currently inside an HTML tag (between < and >).
     * TEXT_TYPE tokens for HTML attributes/class values sit inside tags and must be ignored.
     * Reset at token index 0 to handle successive files.
     */
    private bool $insideTag = false;

    /**
     * Tracks whether we are currently inside a quoted HTML attribute value (between " or ').
     * Prevents a > inside an attribute value (e.g. the arrow in a Stimulus action descriptor
     * like "poll:completed@window->composer#enable") from being misread as the closing > of
     * the tag, which would cause the remainder of the attribute value to be flagged as raw text.
     * Reset at token index 0 to handle successive files.
     */
    private bool $insideAttrValue = false;

    /**
     * The quote character (" or ') that opened the current attribute value.
     * Empty string when $insideAttrValue is false.
     * Reset at token index 0 to handle successive files.
     */
    private string $attrQuote = '';

    /**
     * The index of the first token not yet covered by a reported text-run error.
     * When consecutive TEXT_TYPE tokens (separated only by whitespace) form a run,
     * only the first token triggers an error covering the full run. Subsequent tokens
     * in the same run are skipped to avoid one error per word.
     * Reset at token index 0 to handle successive files.
     */
    private int $nextTextTokenToReport = 0;

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        // Reset per-file state at the start of each new file
        if (0 === $tokenIndex) {
            $this->insideTag = false;
            $this->insideAttrValue = false;
            $this->attrQuote = '';
            $this->nextTextTokenToReport = 0;
        }

        $token = $tokens->get($tokenIndex);

        if ($token->isMatching(Token::STRING_TYPE)) {
            $this->processStringToken($tokenIndex, $tokens, $token);

            return;
        }

        if ($token->isMatching(Token::TEXT_TYPE)) {
            $this->processTextToken($tokenIndex, $tokens, $token);
        }
    }

    private function processStringToken(int $tokenIndex, Tokens $tokens, Token $token): void
    {
        $raw = $token->getValue();
        $value = substr($raw, 1, -1); // strip surrounding quotes

        if (preg_match(self::KEY_PATTERN, $value)) {
            return; // already a valid key
        }

        // Look ahead (skipping whitespace) to find a | punctuation token
        $pipeIndex = $tokens->findNext(
            self::WHITESPACE_TOKEN_TYPES,
            $tokenIndex + 1,
            null,
            true,
        );
        if (false === $pipeIndex) {
            return;
        }

        $pipeToken = $tokens->get($pipeIndex);
        if (!$pipeToken->isMatching(Token::PUNCTUATION_TYPE, '|')) {
            return;
        }

        // Look ahead (skipping whitespace) to find the filter name token
        $filterIndex = $tokens->findNext(
            self::WHITESPACE_TOKEN_TYPES,
            $pipeIndex + 1,
            null,
            true,
        );
        if (false === $filterIndex) {
            return;
        }

        $filterToken = $tokens->get($filterIndex);
        if (!$filterToken->isMatching(Token::FILTER_NAME_TYPE, 'trans')) {
            return;
        }

        $this->addError(
            \sprintf(
                'String "%s" passed to |trans must be a translation key (e.g. "account.login.heading").',
                $value,
            ),
            $token,
        );
    }

    private function processTextToken(int $tokenIndex, Tokens $tokens, Token $token): void
    {
        // If this token belongs to a run already reported by an earlier token, skip it.
        // The insideTag state was already advanced for the full run at that point.
        if ($tokenIndex < $this->nextTextTokenToReport) {
            return;
        }

        // Collect the full text run: this token plus any consecutive TEXT_TYPE tokens
        // that are separated from it only by whitespace (WHITESPACE/EOL/TAB tokens).
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
            // Include the whitespace tokens between the previous text token and this one
            for ($i = $runEndIdx + 1; $i < $nextNonWs; ++$i) {
                $combinedText .= $tokens->get($i)->getValue();
            }
            $combinedText .= $nextToken->getValue();
            $runEndIdx = $nextNonWs;
            $lookFrom = $nextNonWs + 1;
        }

        // Mark all tokens in this run as handled so subsequent calls skip them.
        $this->nextTextTokenToReport = $runEndIdx + 1;

        // Walk the combined text to extract only characters outside HTML tags.
        // Track quoted attribute values so that a > inside an attribute value (e.g. the
        // arrow in a Stimulus action descriptor like "poll:completed@window->composer#enable")
        // is not mistaken for the closing > of the tag itself.
        $outside = '';
        $len = \strlen($combinedText);
        for ($i = 0; $i < $len; ++$i) {
            $ch = $combinedText[$i];
            if ($this->insideTag) {
                if ($this->insideAttrValue) {
                    // Inside a quoted attribute value — only the matching quote exits.
                    if ($ch === $this->attrQuote) {
                        $this->insideAttrValue = false;
                        $this->attrQuote = '';
                    }
                } else {
                    if ('"' === $ch || "'" === $ch) {
                        $this->insideAttrValue = true;
                        $this->attrQuote = $ch;
                    } elseif ('>' === $ch) {
                        $this->insideTag = false;
                    }
                }
            } else {
                if ('<' === $ch) {
                    $this->insideTag = true;
                } else {
                    $outside .= $ch;
                }
            }
        }

        // Decode HTML entities and strip any resulting HTML tags before checking for visible letters.
        $outside = strip_tags(html_entity_decode(trim($outside), \ENT_HTML5, 'UTF-8'));

        if ('' === $outside || !preg_match('/[a-zA-Z]/', $outside)) {
            return;
        }

        $this->addError(
            \sprintf(
                'Raw text "%s" found in template. Wrap it in a translation key and use |trans.',
                $outside,
            ),
            $token,
        );
    }
}
