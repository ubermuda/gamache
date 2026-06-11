<?php

declare(strict_types=1);

namespace Gamache\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConfigurableFixerTrait;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolverInterface;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Analyzer\WhitespacesAnalyzer;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Forces specified PHP attributes to expand their arguments to one per line.
 *
 * Handles every attribute in a #[A(...), B(...)] group independently.
 * Already-correct formatting is detected by string comparison and skipped.
 *
 * @phpstan-type TInputConfig array{attributes?: list<string>, minimum_arguments?: int}
 * @phpstan-type TComputedConfig array{attributes: list<string>, minimum_arguments: int}
 *
 * @implements ConfigurableFixerInterface<TInputConfig, TComputedConfig>
 */
final class MultilineAttributeFixer extends AbstractFixer implements ConfigurableFixerInterface, WhitespacesAwareFixerInterface
{
    /** @use ConfigurableFixerTrait<TInputConfig, TComputedConfig> */
    use ConfigurableFixerTrait;

    #[\Override]
    public function getName(): string
    {
        return 'Gamache/multiline_attribute';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Forces specified PHP attributes to use one-argument-per-line multiline format.',
            [
                new CodeSample(
                    <<<'PHP'
                    <?php
                    #[Route('/path', name: 'route_name', methods: ['GET'])]
                    function foo(): void {}
                    PHP,
                ),
            ],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(\T_ATTRIBUTE);
    }

    protected function createConfigurationDefinition(): FixerConfigurationResolverInterface
    {
        return new FixerConfigurationResolver([
            new FixerOptionBuilder('attributes', 'List of attribute short class names to format as multiline.')
                ->setAllowedTypes(['string[]'])
                ->setDefault(['Route'])
                ->getOption(),
            new FixerOptionBuilder('minimum_arguments', 'Minimum number of arguments required to trigger multiline expansion.')
                ->setAllowedTypes(['int'])
                ->setAllowedValues([static fn (int $v): bool => $v >= 1])
                ->setDefault(1)
                ->getOption(),
        ]);
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        if (null === $this->configuration) {
            return;
        }

        /** @var list<string> $configuredNames */
        $configuredNames = $this->configuration['attributes'];
        $minimumArguments = $this->configuration['minimum_arguments'];

        for ($index = 0, $count = $tokens->count(); $index < $count; ++$index) {
            if (!$tokens[$index]->isGivenKind(\T_ATTRIBUTE)) {
                continue;
            }

            $attributeIndex = $index;
            $closeAttrIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ATTRIBUTE, $attributeIndex);

            // Iterate every attribute in this #[...] group (e.g. #[A(...), B(...)])
            $pos = $tokens->getNextTokenOfKind($attributeIndex, [[\T_STRING], [\T_NS_SEPARATOR]]);

            while (null !== $pos && $pos < $closeAttrIndex) {
                $nameStart = $pos;
                $delimiter = $tokens->getNextTokenOfKind($nameStart, ['(', ',', [CT::T_ATTRIBUTE_CLOSE]]);

                if (null === $delimiter || $delimiter > $closeAttrIndex) {
                    break;
                }

                $lastNameToken = $tokens->getPrevMeaningfulToken($delimiter);
                if (null === $lastNameToken) {
                    break;
                }

                $name = trim($tokens->generatePartialCode($nameStart, $lastNameToken));

                if (!$tokens[$delimiter]->equals('(')) {
                    // Attribute with no arguments — advance past the comma to the next one
                    if (!$tokens[$delimiter]->equals(',')) {
                        break; // reached CT::T_ATTRIBUTE_CLOSE
                    }
                    $pos = $tokens->getNextTokenOfKind($delimiter, [[\T_STRING], [\T_NS_SEPARATOR]]);
                    continue;
                }

                $openParen = $delimiter;
                $closeParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParen);

                if ($this->matchesConfigured($name, $configuredNames)) {
                    $firstArg = $tokens->getNextMeaningfulToken($openParen);
                    if (null !== $firstArg && $firstArg !== $closeParen) {
                        $baseIndent = WhitespacesAnalyzer::detectIndent($tokens, $attributeIndex);
                        $lineEnding = $this->whitespacesConfig->getLineEnding();
                        $argIndent = $baseIndent.$this->whitespacesConfig->getIndent();

                        $arguments = $this->extractArguments($tokens, $openParen, $closeParen);

                        if ([] !== $arguments && count($arguments) >= $minimumArguments) {
                            $expected = $this->buildExpected($arguments, $lineEnding, $argIndent, $baseIndent);
                            $actual = $tokens->generatePartialCode($openParen + 1, $closeParen - 1);

                            if ($expected !== $actual) {
                                $this->replaceInner($tokens, $openParen, $closeParen, $arguments, $lineEnding, $argIndent, $baseIndent);
                                // Re-anchor indices after token insertion/removal
                                $count = $tokens->count();
                                $closeParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParen);
                                $closeAttrIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ATTRIBUTE, $attributeIndex);
                            }
                        }
                    }
                }

                // Advance to the next attribute in the group (after the outer comma, if any)
                $afterClose = $tokens->getNextMeaningfulToken($closeParen);
                if (null === $afterClose || !$tokens[$afterClose]->equals(',') || $afterClose >= $closeAttrIndex) {
                    break;
                }
                $pos = $tokens->getNextTokenOfKind($afterClose, [[\T_STRING], [\T_NS_SEPARATOR]]);
            }

            $count = $tokens->count();
        }
    }

    /**
     * Returns true when the attribute name (full or short) is in the configured list.
     *
     * @param list<string> $configuredNames
     */
    private function matchesConfigured(string $name, array $configuredNames): bool
    {
        $shortName = str_contains($name, '\\')
            ? substr($name, (int) strrpos($name, '\\') + 1)
            : $name;

        return array_any($configuredNames, fn ($configured) => $name === $configured || $shortName === $configured);
    }

    /**
     * Extract arguments from between parentheses, splitting at depth-0 commas.
     * Returns each argument as a whitespace-trimmed list of cloned tokens.
     *
     * @return list<list<Token>>
     */
    private function extractArguments(Tokens $tokens, int $openParen, int $closeParen): array
    {
        $arguments = [];
        $current = [];

        for ($i = $openParen + 1; $i < $closeParen; ++$i) {
            $blockType = Tokens::detectBlockType($tokens[$i]);
            if (null !== $blockType && $blockType['isStart']) {
                $blockEnd = $tokens->findBlockEnd($blockType['type'], $i);
                for ($j = $i; $j <= $blockEnd; ++$j) {
                    $current[] = clone $tokens[$j];
                }
                $i = $blockEnd;
                continue;
            }

            if ($tokens[$i]->equals(',')) {
                $trimmed = $this->trimWhitespace($current);
                if ([] !== $trimmed) {
                    $arguments[] = $trimmed;
                }
                $current = [];
                continue;
            }

            $current[] = clone $tokens[$i];
        }

        // Capture the last argument (may have no trailing comma in the source)
        $trimmed = $this->trimWhitespace($current);
        if ([] !== $trimmed) {
            $arguments[] = $trimmed;
        }

        return $arguments;
    }

    /**
     * Trim leading and trailing whitespace tokens from a list.
     *
     * @param list<Token> $tokenList
     *
     * @return list<Token>
     */
    private function trimWhitespace(array $tokenList): array
    {
        $start = 0;
        $end = count($tokenList) - 1;

        while ($start <= $end && $tokenList[$start]->isWhitespace()) {
            ++$start;
        }

        while ($end >= $start && $tokenList[$end]->isWhitespace()) {
            --$end;
        }

        if ($start > $end) {
            return [];
        }

        return array_values(array_slice($tokenList, $start, $end - $start + 1));
    }

    /**
     * Build the expected inner content string (between '(' and ')') for comparison.
     *
     * @param list<list<Token>> $arguments
     */
    private function buildExpected(array $arguments, string $lineEnding, string $argIndent, string $baseIndent): string
    {
        $result = '';
        foreach ($arguments as $argTokens) {
            $argCode = implode('', array_map(static fn (Token $t): string => $t->getContent(), $argTokens));
            $result .= $lineEnding.$argIndent.$argCode.',';
        }

        return $result.$lineEnding.$baseIndent;
    }

    /**
     * Replace all tokens between '(' and ')' with properly formatted content.
     *
     * @param list<list<Token>> $arguments
     */
    private function replaceInner(
        Tokens $tokens,
        int $openParen,
        int $closeParen,
        array $arguments,
        string $lineEnding,
        string $argIndent,
        string $baseIndent,
    ): void {
        $newTokens = [];
        foreach ($arguments as $argTokens) {
            $newTokens[] = new Token([\T_WHITESPACE, $lineEnding.$argIndent]);
            foreach ($argTokens as $token) {
                $newTokens[] = $token;
            }
            $newTokens[] = new Token(',');
        }
        $newTokens[] = new Token([\T_WHITESPACE, $lineEnding.$baseIndent]);

        for ($i = $openParen + 1; $i < $closeParen; ++$i) {
            $tokens->clearAt($i);
        }

        $tokens->insertAt($openParen + 1, $newTokens);
    }
}
