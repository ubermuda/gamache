<?php

declare(strict_types=1);

namespace Gamache\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Analyzer\WhitespacesAnalyzer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Ensures constructor parameters decorated with attributes are separated by a blank line.
 */
final class BlankLineBetweenAttributedParametersFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    #[\Override]
    public function getName(): string
    {
        return 'Gamache/blank_line_between_attributed_parameters';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Constructor parameters with attributes must be separated by a blank line.',
            [
                new CodeSample(
                    <<<'PHP'
                    <?php
                    class Foo {
                        public function __construct(
                            #[Bar]
                            public string $a,
                            #[Baz]
                            public string $b,
                        ) {}
                    }
                    PHP,
                ),
            ],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(\T_ATTRIBUTE);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = 0, $count = $tokens->count(); $index < $count; ++$index) {
            if (!$tokens[$index]->isGivenKind(\T_STRING) || '__construct' !== $tokens[$index]->getContent()) {
                continue;
            }

            $openParen = $tokens->getNextTokenOfKind($index, ['(']);
            if (null === $openParen) {
                continue;
            }

            $closeParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParen);

            $this->fixConstructor($tokens, $openParen, $closeParen);
        }
    }

    private function fixConstructor(Tokens $tokens, int $openParen, int $closeParen): void
    {
        $separators = $this->findParameterSeparators($tokens, $openParen, $closeParen);

        $indent = WhitespacesAnalyzer::detectIndent($tokens, $openParen);
        $lineEnding = $this->whitespacesConfig->getLineEnding();
        $paramIndent = $indent.$this->whitespacesConfig->getIndent();

        foreach ($separators as $i => $sepIndex) {
            if (0 === $i) {
                // Never add a blank line before the first parameter.
                continue;
            }

            $nextMeaningful = $tokens->getNextMeaningfulToken($sepIndex);
            if (null === $nextMeaningful || !$tokens[$nextMeaningful]->isGivenKind(\T_ATTRIBUTE)) {
                continue;
            }

            $whitespaceIndex = $sepIndex + 1;

            if ($tokens[$whitespaceIndex]->isWhitespace()) {
                $content = $tokens[$whitespaceIndex]->getContent();
                if (substr_count($content, "\n") < 2) {
                    $tokens[$whitespaceIndex] = new Token([\T_WHITESPACE, $lineEnding.$lineEnding.$paramIndent]);
                }
            } else {
                $tokens->insertAt($whitespaceIndex, new Token([\T_WHITESPACE, $lineEnding.$lineEnding.$paramIndent]));
            }
        }
    }

    /**
     * Returns the indices of '(' and each ',' that directly separates parameters (depth 0).
     *
     * @return list<int>
     */
    private function findParameterSeparators(Tokens $tokens, int $openParen, int $closeParen): array
    {
        $separators = [$openParen];

        $index = $openParen + 1;
        while ($index < $closeParen) {
            $blockType = Tokens::detectBlockType($tokens[$index]);

            if (null !== $blockType && $blockType['isStart']) {
                $index = $tokens->findBlockEnd($blockType['type'], $index);
                ++$index;
                continue;
            }

            if ($tokens[$index]->equals(',')) {
                $separators[] = $index;
            }

            ++$index;
        }

        return $separators;
    }
}
