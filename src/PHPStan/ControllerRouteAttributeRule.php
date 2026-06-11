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
use Symfony\Component\Routing\Attribute\Route;

/**
 * @implements Rule<Class_>
 */
final readonly class ControllerRouteAttributeRule implements Rule
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

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (Route::class === $scope->resolveName($attr->name)) {
                    return [];
                }
            }
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Controller %s must have a #[Route] attribute.',
                $node->name->name,
            ))
            ->identifier('controller.missingRouteAttribute')
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

        if (null === $node->extends) {
            return false;
        }

        return $this->controllerBaseClass === $scope->resolveName($node->extends);
    }
}
