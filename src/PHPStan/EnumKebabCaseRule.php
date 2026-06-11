<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Enum_>
 */
final readonly class EnumKebabCaseRule implements Rule
{
    private const string KEBAB_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    public function getNodeType(): string
    {
        return Enum_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Enum_);

        if (null === $node->scalarType || 'string' !== $node->scalarType->name) {
            return [];
        }

        $errors = [];
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Node\Stmt\EnumCase || !$stmt->expr instanceof Node\Scalar\String_) {
                continue;
            }
            $value = $stmt->expr->value;
            if (!preg_match(self::KEBAB_PATTERN, $value)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Enum case value "%s" must be kebab-case (e.g. "my-value").',
                    $value,
                ))
                ->identifier('enum.notKebabCase')
                ->line($stmt->getLine())
                ->build();
            }
        }

        return $errors;
    }
}
