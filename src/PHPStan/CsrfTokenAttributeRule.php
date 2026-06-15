<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags imperative CSRF validation inside controller subclasses:
 *   - $this->isCsrfTokenValid(...) (the AbstractController helper), and
 *   - $manager->isTokenValid(...) where the receiver is a CsrfTokenManagerInterface.
 *
 * Operates on the class node (like the other controller rules) so the
 * controller-base subclass check can fall back to an AST parent check when the
 * base class is not loadable in the reflection provider (test fixtures).
 *
 * @implements Rule<Class_>
 */
final readonly class CsrfTokenAttributeRule implements Rule
{
    private const string CSRF_MANAGER_INTERFACE = 'Symfony\Component\Security\Csrf\CsrfTokenManagerInterface';

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $controllerBaseClass,
        private string $csrfTokenAttributeClass,
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

        if (!$this->isControllerSubclass($fqcn, $node, $scope)) {
            return [];
        }

        $errors = [];

        /** @var list<MethodCall> $methodCalls */
        $methodCalls = (new NodeFinder())->findInstanceOf($node, MethodCall::class);

        foreach ($methodCalls as $methodCall) {
            if (!$methodCall->name instanceof Node\Identifier) {
                continue;
            }

            $method = $methodCall->name->name;

            $isThisHelper = 'isCsrfTokenValid' === $method
                && $methodCall->var instanceof Node\Expr\Variable
                && 'this' === $methodCall->var->name;

            $isManagerCall = 'isTokenValid' === $method
                && (new ObjectType(self::CSRF_MANAGER_INTERFACE))->isSuperTypeOf($scope->getType($methodCall->var))->yes();

            if (!$isThisHelper && !$isManagerCall) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Controller must not call %s() to validate CSRF tokens imperatively. %s; validation runs in the listener before the action.',
                $method,
                $this->suggestion(),
            ))
                ->identifier('controller.csrfTokenAttribute')
                ->line($methodCall->getLine())
                ->build();
        }

        return $errors;
    }

    private function isControllerSubclass(string $fqcn, Class_ $node, Scope $scope): bool
    {
        if ($this->reflectionProvider->hasClass($fqcn)) {
            return $this->reflectionProvider->getClass($fqcn)->isSubclassOf($this->controllerBaseClass);
        }

        // Reflection not available (e.g. the controller base class is not
        // loadable in the test reflection provider): fall back to an AST
        // immediate-parent check against the configured base class.
        if (null === $node->extends) {
            return false;
        }

        return $this->controllerBaseClass === $scope->resolveName($node->extends);
    }

    private function suggestion(): string
    {
        return '' !== $this->csrfTokenAttributeClass
            ? \sprintf('Use the #[%s] attribute instead', $this->csrfTokenAttributeClass)
            : 'Use a declarative CSRF attribute instead';
    }
}
