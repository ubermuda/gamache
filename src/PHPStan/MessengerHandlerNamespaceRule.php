<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A Messenger handler and the message it handles must live in the same
 * namespace. Side-by-side placement keeps the message/handler pair easy to
 * find; a handler whose message drifts to another namespace passes CI
 * silently without this rule.
 *
 * @implements Rule<Class_>
 */
final readonly class MessengerHandlerNamespaceRule implements Rule
{
    private const string ATTRIBUTE = 'Symfony\Component\Messenger\Attribute\AsMessageHandler';

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

        $handlerFqcn = $node->namespacedName->toString();
        $errors = [];

        // Class-level #[AsMessageHandler]
        $classAttribute = $this->findAttribute($node->attrGroups, $scope);
        if (null !== $classAttribute) {
            $messageFqcn = $this->resolveMessageClass($node, $classAttribute, $scope);
            if (null !== $messageFqcn) {
                $error = $this->compareNamespaces($handlerFqcn, $messageFqcn, $node->getLine());
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }

        // Method-level #[AsMessageHandler]
        foreach ($node->getMethods() as $method) {
            if (null === $this->findAttribute($method->attrGroups, $scope)) {
                continue;
            }

            $messageFqcn = $this->messageClassFromMethod($method, $scope);
            if (null !== $messageFqcn) {
                $error = $this->compareNamespaces($handlerFqcn, $messageFqcn, $method->getLine());
                if (null !== $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /** @param array<Node\AttributeGroup> $attrGroups */
    private function findAttribute(array $attrGroups, Scope $scope): ?Node\Attribute
    {
        foreach ($attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (self::ATTRIBUTE === $scope->resolveName($attribute->name)) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * Resolves the handled message class for a class-level attribute: an
     * explicit `handles:` argument wins, then the method named by `method:`
     * (default `__invoke`).
     */
    private function resolveMessageClass(Class_ $class, Node\Attribute $attribute, Scope $scope): ?string
    {
        $methodName = '__invoke';
        foreach ($attribute->args as $arg) {
            if (null === $arg->name) {
                continue;
            }
            if ('handles' === $arg->name->name) {
                return $this->classNameFromExpr($arg->value, $scope);
            }
            if ('method' === $arg->name->name && $arg->value instanceof String_) {
                $methodName = $arg->value->value;
            }
        }

        foreach ($class->getMethods() as $method) {
            if ($method->name->name === $methodName) {
                return $this->messageClassFromMethod($method, $scope);
            }
        }

        return null;
    }

    private function messageClassFromMethod(ClassMethod $method, Scope $scope): ?string
    {
        $param = $method->params[0] ?? null;
        if (!$param instanceof Param) {
            return null;
        }

        $type = $param->type;
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }
        if (!$type instanceof Name) {
            return null;
        }

        return $scope->resolveName($type);
    }

    private function classNameFromExpr(Node\Expr $expr, Scope $scope): ?string
    {
        if ($expr instanceof ClassConstFetch && $expr->class instanceof Name && $expr->name instanceof Node\Identifier && 'class' === $expr->name->name) {
            return $scope->resolveName($expr->class);
        }
        if ($expr instanceof String_) {
            return $expr->value;
        }

        return null;
    }

    private function compareNamespaces(string $handlerFqcn, string $messageFqcn, int $line): ?RuleError
    {
        if ($this->namespaceOf($handlerFqcn) === $this->namespaceOf($messageFqcn)) {
            return null;
        }

        return RuleErrorBuilder::message(sprintf(
            'Message handler %s and its message %s must live in the same namespace.',
            $handlerFqcn,
            $messageFqcn,
        ))
        ->identifier('messenger.handlerNamespaceMismatch')
        ->line($line)
        ->build();
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? '' : substr($fqcn, 0, $pos);
    }
}
