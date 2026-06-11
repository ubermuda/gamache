<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use Doctrine\ORM\Mapping\Entity;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class EntityAsymmetricVisibilityRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        if (null === $node->name) {
            return [];
        }

        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name;

        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return [];
        }

        if (!$this->hasDoctrineEntityAttribute($this->reflectionProvider->getClass($fqcn))) {
            return [];
        }

        // Find constructor
        $constructor = array_find(
            $node->stmts,
            fn ($stmt) => $stmt instanceof ClassMethod && '__construct' === $stmt->name->name,
        );

        if (!$constructor instanceof ClassMethod) {
            return [];
        }

        $errors = [];
        foreach ($constructor->params as $param) {
            // Skip non-promoted params (flags === 0 means no visibility modifier)
            if (0 === $param->flags) {
                continue;
            }

            // $id is exempt
            if ($param->var instanceof Node\Expr\Variable && 'id' === $param->var->name) {
                continue;
            }

            if (!$param->isPrivateSet()) {
                continue;
            }

            $varName = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? $param->var->name
                : '?';

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Entity property $%s must not use private(set) asymmetric visibility. '
                .'Use plain public visibility instead.',
                $varName,
            ))
            ->identifier('entity.privateSet')
            ->line($param->getLine())
            ->build();
        }

        return $errors;
    }

    private function hasDoctrineEntityAttribute(ClassReflection $reflection): bool
    {
        foreach ($reflection->getAttributes() as $attribute) {
            $name = $attribute->getName();
            if (Entity::class === $name || str_ends_with($name, '\\Entity')) {
                return true;
            }
        }

        return false;
    }
}
