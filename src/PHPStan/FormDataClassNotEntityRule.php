<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use Doctrine\ORM\Mapping\Entity;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<ClassMethod>
 */
final readonly class FormDataClassNotEntityRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof ClassMethod);

        if ('configureOptions' !== $node->name->name) {
            return [];
        }

        foreach ($node->stmts ?? [] as $stmt) {
            if (!$stmt instanceof Node\Stmt\Expression) {
                continue;
            }
            $call = $stmt->expr;
            if (!$call instanceof Node\Expr\MethodCall) {
                continue;
            }
            if (!$call->name instanceof Node\Identifier || 'setDefaults' !== $call->name->name) {
                continue;
            }

            $arg = $call->args[0] ?? null;
            if (!$arg instanceof Node\Arg || !$arg->value instanceof Node\Expr\Array_) {
                continue;
            }

            foreach ($arg->value->items as $item) {
                if (!$item instanceof Node\Expr\ArrayItem) {
                    continue;
                }
                if (!$item->key instanceof Node\Scalar\String_ || 'data_class' !== $item->key->value) {
                    continue;
                }

                $classReflection = $this->findClassReflection($item->value, $scope);
                if (null === $classReflection) {
                    continue;
                }

                if ($this->hasDoctrineEntityAttribute($classReflection)) {
                    return [
                        RuleErrorBuilder::message(sprintf(
                            'Form data_class %s is a Doctrine entity. Use a DTO instead.',
                            $classReflection->getName(),
                        ))
                        ->identifier('form.dataClassIsEntity')
                        ->line($call->getLine())
                        ->build(),
                    ];
                }
            }
        }

        return [];
    }

    private function findClassReflection(Node\Expr $value, Scope $scope): ?ClassReflection
    {
        if (
            !$value instanceof Node\Expr\ClassConstFetch
            || !$value->name instanceof Node\Identifier
            || 'class' !== $value->name->name
            || !$value->class instanceof Node\Name
        ) {
            return null;
        }

        $fqcn = $scope->resolveName($value->class);

        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return null;
        }

        return $this->reflectionProvider->getClass($fqcn);
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
