<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class HandlerShapeRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        if (null === $node->name || null === $node->namespacedName) {
            return [];
        }

        $fqcn = $node->namespacedName->toString();
        if (!preg_match('/\\\\Command\\\\/', $fqcn) || !str_ends_with($fqcn, 'Handler')) {
            return [];
        }

        $errors = [];

        if (!$node->isFinal() || !$node->isReadonly()) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Handler %s must be declared as "final readonly class".',
                $node->name->name,
            ))
            ->identifier('handler.notFinalReadonly')
            ->line($node->getLine())
            ->build();
        }

        $publicMethods = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->isPublic() && '__construct' !== $stmt->name->name) {
                $publicMethods[] = $stmt->name->name;
            }
        }

        if ($publicMethods !== ['__invoke']) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Handler %s must declare exactly one public method: __invoke().',
                $node->name->name,
            ))
            ->identifier('handler.invalidShape')
            ->line($node->getLine())
            ->build();
        }

        return $errors;
    }
}
