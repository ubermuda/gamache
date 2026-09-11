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
 * A controller must never read or mutate persistent state directly: every read
 * and every write goes through a Command/Handler, keeping the controller a thin
 * request->handler->response shell.
 *
 * The rule flags any call inside a controller on an injected Doctrine
 * persistence collaborator — the EntityManager/ObjectManager, the DBAL
 * Connection, or a repository. Handlers are invoked as a callable
 * (`($this->handler)(...)`), which is a FuncCall rather than a MethodCall, so
 * delegation is exempt; inherited helpers (`$this->getUser()`, `$this->render()`)
 * are called on `$this`, not on a persistence collaborator, so they are exempt too.
 *
 * @implements Rule<Class_>
 */
final readonly class ControllerNoDirectStateAccessRule implements Rule
{
    private DirectStateAccessFinder $finder;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $controllerBaseClass,
    ) {
        $this->finder = new DirectStateAccessFinder($reflectionProvider);
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

        if (!$this->isController($node, $scope)) {
            return [];
        }

        return array_map(
            fn (array $finding): RuleError => RuleErrorBuilder::message(sprintf(
                'Controller %s must not access persistent state directly (%s()); read and write through a Command/Handler.',
                $node->name->name,
                $finding[0],
            ))
            ->identifier('controller.directStateAccess')
            ->line($finding[1])
            ->build(),
            $this->finder->findings($node, $scope),
        );
    }

    private function isController(Class_ $node, Scope $scope): bool
    {
        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name ?? '';

        if ($this->reflectionProvider->hasClass($fqcn)) {
            return $this->reflectionProvider->getClass($fqcn)->isSubclassOf($this->controllerBaseClass);
        }

        // Reflection not available (e.g. global-namespace fixture): fall back to AST parent check.
        return null !== $node->extends
            && $this->controllerBaseClass === $scope->resolveName($node->extends);
    }
}
