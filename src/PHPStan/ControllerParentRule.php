<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class ControllerParentRule implements Rule
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

        $className = $node->name->name;
        if (!str_ends_with($className, 'Controller')) {
            return [];
        }

        // Resolve the FQCN of this class using namespacedName (set by PHPStan's name resolver).
        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $className;

        if ($this->controllerBaseClass === $fqcn) {
            return [];
        }

        // If PHPStan has no reflection for this class, skip — it will be caught
        // by other rules (e.g. unknown class). Only report when we can confirm
        // the hierarchy.
        if (!$this->reflectionProvider->hasClass($fqcn)) {
            // Fall back to AST check: resolve the parent name via scope and
            // compare directly.
            if (null === $node->extends) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Controller class %s must extend %s.',
                        $className,
                        $this->shortName($this->controllerBaseClass),
                    ))
                    ->identifier('controller.missingAppControllerParent')
                    ->build(),
                ];
            }

            $resolvedParent = $scope->resolveName($node->extends);
            if ($this->controllerBaseClass === $resolvedParent) {
                return [];
            }

            return [
                RuleErrorBuilder::message(sprintf(
                    'Controller class %s must extend %s.',
                    $className,
                    $this->shortName($this->controllerBaseClass),
                ))
                ->identifier('controller.missingAppControllerParent')
                ->build(),
            ];
        }

        $reflection = $this->reflectionProvider->getClass($fqcn);
        if ($reflection->isSubclassOf($this->controllerBaseClass)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Controller class %s must extend %s.',
                $className,
                $this->shortName($this->controllerBaseClass),
            ))
            ->identifier('controller.missingAppControllerParent')
            ->build(),
        ];
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
