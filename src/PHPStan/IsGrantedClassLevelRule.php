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
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @implements Rule<Class_>
 */
final readonly class IsGrantedClassLevelRule implements Rule
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

        $errors = [];
        foreach ($node->getMethods() as $method) {
            foreach ($method->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if (IsGranted::class !== $scope->resolveName($attr->name)) {
                        continue;
                    }

                    $errors[] = RuleErrorBuilder::message(sprintf(
                        '#[IsGranted] on %s::%s() must be declared at the class level, not on the method (single-action controllers carry access control on the class, like #[Route]). The subject still resolves from the controller arguments.',
                        $node->name->name,
                        $method->name->name,
                    ))
                    ->identifier('controller.isGrantedNotClassLevel')
                    ->line($attr->getLine())
                    ->build();
                }
            }
        }

        return $errors;
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
