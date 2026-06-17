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
     * HTML elements whose textual content is code, not human-readable copy, and
     * must never be flagged as raw text (e.g. CSS in <style>, JS in <script>).
     */
    private const array RAW_TEXT_ELEMENTS = ['style', 'script'];

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
     * While inside an HTML tag, tracks whether we are still reading the tag name
     * (the run of letters/digits right after < or </). Used to capture the tag name
     * so <style>/<script> elements can be detected.
     * Reset at token index 0 to handle successive files.
     */
    private bool $readingTagName = false;

    /**
     * The tag name captured for the tag currently being read.
     * Reset at token index 0 to handle successive files.
     */
    private string $tagName = '';

    /**
     * Whether the tag currently being read is a closing tag (</…>).
     * Reset at token index 0 to handle successive files.
     */
    private bool $tagIsClosing = false;

    /**
     * The lowercased name of the raw-text element we are currently inside
     * (one of self::RAW_TEXT_ELEMENTS), or null when not inside one. While set,
     * all text is skipped until the matching closing tag.
     * Reset at token index 0 to handle successive files.
     */
    private ?string $rawElement = null;

    /**
     * The index of the first token not yet covered by a reported text-run error.
     * When consecutive TEXT_TYPE tokens (separated only by whitespace) form a run,
     * only the first token triggers an error covering the full run. Subsequent tokens
     * in the same run are skipped to avoid one error per word.
     * Reset at token index 0 to handle successive files.
     */
    private int $nextTextTokenToReport = 0;

    /**
     * Whether the current file matched one of the excluded paths and should be skipped
     * entirely. Reset at token index 0 to handle successive files.
     */
    private bool $skipFile = false;

    /**
     * Token indices whose TEXT_TYPE value sits inside a {% trans %}…{% endtrans %} block.
     * Such content is a translation key, not raw text, and is validated as a key instead
     * of being flagged. Recomputed at token index 0 for each file.
     *
     * @var array<int, true>
     */
    private array $insideTransIdx = [];

    /**
     * @param list<string> $excludedPaths fnmatch() patterns checked against each file's
     *                                    path; matching files are skipped entirely. Use to
     *                                    exempt areas not yet translated (e.g. '*\/admin\/*').
     */
    public function __construct(private readonly array $excludedPaths = [])
    {
    }

    protected function process(int $tokenIndex, Tokens $tokens): void
    {
        // Reset per-file state at the start of each new file
        if (0 === $tokenIndex) {
            $this->insideTag = false;
            $this->insideAttrValue = false;
            $this->attrQuote = '';
            $this->readingTagName = false;
            $this->tagName = '';
            $this->tagIsClosing = false;
            $this->rawElement = null;
            $this->nextTextTokenToReport = 0;
            $this->skipFile = $this->isExcluded($tokens->get($tokenIndex)->getFilename());
            $this->insideTransIdx = $this->skipFile ? [] : $this->collectTransBlockIndexes($tokens);
        }

        if ($this->skipFile) {
            return;
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

    private function isExcluded(string $filename): bool
    {
        foreach ($this->excludedPaths as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, true>
     */
    private function collectTransBlockIndexes(Tokens $tokens): array
    {
        $inside = false;
        $indexes = [];

        foreach ($tokens->toArray() as $index => $token) {
            if ($token->isMatching(Token::BLOCK_NAME_TYPE, 'trans')) {
                $inside = true;

                continue;
            }

            if ($token->isMatching(Token::BLOCK_NAME_TYPE, 'endtrans')) {
                $inside = false;

                continue;
            }

            if ($inside && $token->isMatching(Token::TEXT_TYPE)) {
                $indexes[$index] = true;
            }
        }

        return $indexes;
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

        if (isset($this->insideTransIdx[$tokenIndex])) {
            $this->processTransBlockText($tokenIndex, $tokens, $token);

            return;
        }

        // Collect the full text run: this token plus any consecutive TEXT_TYPE tokens
        // that are separated from it only by whitespace (WHITESPACE/EOL/TAB tokens).
        // A run stops at a token that belongs to a {% trans %} block, which is handled
        // separately as a translation key.
        $combinedText = $token->getValue();
        $runEndIdx = $tokenIndex;
        $lookFrom = $tokenIndex + 1;

        while (true) {
            $nextNonWs = $tokens->findNext(self::WHITESPACE_TOKEN_TYPES, $lookFrom, null, true);
            if (false === $nextNonWs) {
                break;
            }
            $nextToken = $tokens->get($nextNonWs);
            if (!$nextToken->isMatching(Token::TEXT_TYPE) || isset($this->insideTransIdx[$nextNonWs])) {
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

        $outside = $this->extractVisibleText($combinedText);

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

    /**
     * Validates the text content of a {% trans %}…{% endtrans %} block: it must be a
     * translation key, not natural language.
     */
    private function processTransBlockText(int $tokenIndex, Tokens $tokens, Token $token): void
    {
        $combinedText = $token->getValue();
        $runEndIdx = $tokenIndex;
        $lookFrom = $tokenIndex + 1;

        while (true) {
            $nextNonWs = $tokens->findNext(self::WHITESPACE_TOKEN_TYPES, $lookFrom, null, true);
            if (false === $nextNonWs) {
                break;
            }
            $nextToken = $tokens->get($nextNonWs);
            if (!$nextToken->isMatching(Token::TEXT_TYPE) || !isset($this->insideTransIdx[$nextNonWs])) {
                break;
            }
            for ($i = $runEndIdx + 1; $i < $nextNonWs; ++$i) {
                $combinedText .= $tokens->get($i)->getValue();
            }
            $combinedText .= $nextToken->getValue();
            $runEndIdx = $nextNonWs;
            $lookFrom = $nextNonWs + 1;
        }

        $this->nextTextTokenToReport = $runEndIdx + 1;

        $content = trim($combinedText);
        if ('' === $content || preg_match(self::KEY_PATTERN, $content)) {
            return;
        }

        $this->addError(
            \sprintf(
                'Content "%s" inside {%% trans %%} must be a translation key (e.g. "account.login.heading").',
                $content,
            ),
            $token,
        );
    }

    /**
     * Walks a text run and returns only the characters that are visible page copy:
     * everything outside HTML tags, with the content of <style>/<script> elements
     * skipped and remaining HTML entities/tags decoded away.
     *
     * Tracks quoted attribute values so that a > inside an attribute value (e.g. the
     * arrow in a Stimulus action descriptor like "poll:completed@window->composer#enable")
     * is not mistaken for the closing > of the tag itself. State persists across runs so
     * that tags and raw-text elements split by Twig output (e.g. <style>{{ css }}</style>)
     * are still handled correctly.
     */
    private function extractVisibleText(string $combinedText): string
    {
        $outside = '';
        $len = \strlen($combinedText);

        for ($i = 0; $i < $len; ++$i) {
            $ch = $combinedText[$i];

            if (null !== $this->rawElement) {
                // Inside <style>/<script>: skip everything until the matching closing tag.
                $closing = '</'.$this->rawElement;
                if ('<' === $ch && 0 === substr_compare($combinedText, $closing, $i, \strlen($closing), true)) {
                    $this->rawElement = null;
                    // Fall through so the '<' below is handled as a tag start.
                } else {
                    continue;
                }
            }

            if ($this->insideTag) {
                if ($this->insideAttrValue) {
                    // Inside a quoted attribute value — only the matching quote exits.
                    if ($ch === $this->attrQuote) {
                        $this->insideAttrValue = false;
                        $this->attrQuote = '';
                    }

                    continue;
                }

                if ($this->readingTagName) {
                    if ('/' === $ch && '' === $this->tagName) {
                        $this->tagIsClosing = true;

                        continue;
                    }
                    if (ctype_alnum($ch)) {
                        $this->tagName .= $ch;

                        continue;
                    }
                    // Any other character ends the tag name; fall through to handle it.
                    $this->readingTagName = false;
                }

                if ('"' === $ch || "'" === $ch) {
                    $this->insideAttrValue = true;
                    $this->attrQuote = $ch;
                } elseif ('>' === $ch) {
                    $this->insideTag = false;
                    if (!$this->tagIsClosing && \in_array(strtolower($this->tagName), self::RAW_TEXT_ELEMENTS, true)) {
                        $this->rawElement = strtolower($this->tagName);
                    }
                    $this->tagName = '';
                    $this->tagIsClosing = false;
                }

                continue;
            }

            if ('<' === $ch) {
                $this->insideTag = true;
                $this->readingTagName = true;
                $this->tagName = '';
                $this->tagIsClosing = false;
            } else {
                $outside .= $ch;
            }
        }

        // Decode HTML entities and strip any resulting HTML tags before checking for visible letters.
        return strip_tags(html_entity_decode(trim($outside), \ENT_HTML5, 'UTF-8'));
    }
}
