<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use Doctrine\Migrations\AbstractMigration;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final readonly class MigrationDescriptionRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof ClassMethod);

        if ('getDescription' !== $node->name->name) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !$classReflection->isSubclassOf(AbstractMigration::class)) {
            return [];
        }

        foreach ($node->stmts ?? [] as $stmt) {
            if (!$stmt instanceof Node\Stmt\Return_) {
                continue;
            }

            if ($stmt->expr instanceof Node\Scalar\String_ && '' !== $stmt->expr->value) {
                return [];
            }

            return [
                RuleErrorBuilder::message('Migration::getDescription() must return a non-empty string literal.')
                    ->identifier('migration.emptyDescription')
                    ->line($stmt->getLine())
                    ->build(),
            ];
        }

        return [];
    }
}
