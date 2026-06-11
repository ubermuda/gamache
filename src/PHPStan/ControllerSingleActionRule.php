<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class ControllerSingleActionRule implements Rule
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $controllerBaseClass,
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

        if (!$this->isControllerSubclass($node, $scope)) {
            return [];
        }

        $publicMethods = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->isPublic() && '__construct' !== $stmt->name->name) {
                $publicMethods[] = $stmt->name->name;
            }
        }

        if (['__invoke'] === $publicMethods) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Controller %s must have exactly one public method: __invoke().',
                $node->name->name,
            ))
            ->identifier('controller.notSingleAction')
            ->build(),
        ];
    }

    private function isControllerSubclass(Class_ $node, Scope $scope): bool
    {
        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : ($node->name->name ?? '');

        if ('' === $fqcn || $this->controllerBaseClass === $fqcn) {
            return false;
        }

        if ($this->reflectionProvider->hasClass($fqcn)) {
            return $this->reflectionProvider->getClass($fqcn)->isSubclassOf($this->controllerBaseClass);
        }

        // AST fallback for test fixtures / unresolvable classes
        if (null === $node->extends) {
            return false;
        }

        return $this->controllerBaseClass === $scope->resolveName($node->extends);
    }
}
