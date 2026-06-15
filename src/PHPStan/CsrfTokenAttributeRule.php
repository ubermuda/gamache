<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionClass;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Flags imperative CSRF validation inside controller subclasses:
 *   - $this->isCsrfTokenValid(...) (the AbstractController helper), and
 *   - $manager->isTokenValid(...) where the receiver is a CsrfTokenManagerInterface.
 *
 * Operates on the MethodCall node (not the class node) so that $scope is
 * statement-level: this is what lets $scope->getType($node->var) correctly
 * resolve a property-injected receiver such as $this->csrfTokenManager to
 * CsrfTokenManagerInterface. At class-declaration scope that type would be
 * mixed and the manager-via-property pattern would never be flagged.
 *
 * @implements Rule<MethodCall>
 */
final readonly class CsrfTokenAttributeRule implements Rule
{
    private const string CSRF_MANAGER_INTERFACE = 'Symfony\Component\Security\Csrf\CsrfTokenManagerInterface';

    public function __construct(
        private string $controllerBaseClass,
        private string $csrfTokenAttributeClass,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof MethodCall);

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !$this->isControllerSubclass($classReflection)) {
            return [];
        }

        $method = $node->name->name;

        $isThisHelper = 'isCsrfTokenValid' === $method
            && $node->var instanceof Node\Expr\Variable
            && 'this' === $node->var->name;

        $isManagerCall = 'isTokenValid' === $method
            && (new ObjectType(self::CSRF_MANAGER_INTERFACE))->isSuperTypeOf($scope->getType($node->var))->yes();

        if (!$isThisHelper && !$isManagerCall) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Controller must not call %s() to validate CSRF tokens imperatively. %s; validation runs in the listener before the action.',
                $method,
                $this->suggestion(),
            ))
                ->identifier('controller.csrfTokenAttribute')
                ->line($node->getLine())
                ->build(),
        ];
    }

    /**
     * Determine whether the enclosing class is a subclass of the configured
     * controller base class.
     *
     * The fast path is ClassReflection::isSubclassOf(), which is correct
     * whenever the full ancestry chain is loadable (e.g. real usage, where
     * Symfony's AbstractController is present). When the base class' own parent
     * is NOT loadable — as in the gamache test harness, which has no
     * framework-bundle, so App\Controller\AppController extends an unloadable
     * AbstractController — PHPStan cannot build the ancestry and isSubclassOf()
     * returns false even for a direct subclass. In that case we walk the raw
     * parent-class-name chain straight off the native (AST-backed) reflection,
     * which still exposes each immediate parent's name string.
     */
    private function isControllerSubclass(ClassReflection $classReflection): bool
    {
        if ($classReflection->isSubclassOf($this->controllerBaseClass)) {
            return true;
        }

        $reflection = $classReflection->getNativeReflection();
        // Enums cannot extend a controller; only walk class reflections.
        if (!$reflection instanceof ReflectionClass) {
            return false;
        }

        while (true) {
            $parentName = $reflection->getParentClassName();
            if (null === $parentName) {
                return false;
            }

            if ($this->controllerBaseClass === $parentName) {
                return true;
            }

            $parent = $reflection->getParentClass();
            if (false === $parent) {
                return false;
            }

            $reflection = $parent;
        }
    }

    private function suggestion(): string
    {
        return '' !== $this->csrfTokenAttributeClass
            ? \sprintf('Use the #[%s] attribute instead', $this->csrfTokenAttributeClass)
            : 'Use a declarative CSRF attribute instead';
    }
}
