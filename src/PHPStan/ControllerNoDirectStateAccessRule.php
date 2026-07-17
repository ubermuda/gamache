<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
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
    private const array PERSISTENCE_TYPES = [
        'Doctrine\Persistence\ObjectManager',
        'Doctrine\Persistence\ObjectRepository',
        'Doctrine\DBAL\Connection',
    ];

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

        if (!$this->isController($node, $scope)) {
            return [];
        }

        $persistenceProperties = $this->persistenceProperties($node, $scope);
        if ([] === $persistenceProperties) {
            return [];
        }

        $finder = new NodeFinder();

        /** @var list<array{string, int}> $findings */
        $findings = [];

        /** @var MethodCall[] $calls */
        $calls = $finder->findInstanceOf($node->stmts, MethodCall::class);
        foreach ($calls as $call) {
            if (
                !$call->var instanceof PropertyFetch
                || !$call->var->var instanceof Variable
                || 'this' !== $call->var->var->name
                || !$call->var->name instanceof Identifier
                || !$call->name instanceof Identifier
            ) {
                continue;
            }

            if (!\in_array($call->var->name->name, $persistenceProperties, true)) {
                continue;
            }

            $findings[] = [$call->name->name, $call->getLine()];
        }

        usort($findings, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return array_map(
            fn (array $finding): RuleError => RuleErrorBuilder::message(sprintf(
                'Controller %s must not access persistent state directly (%s()); read and write through a Command/Handler.',
                $node->name->name,
                $finding[0],
            ))
            ->identifier('controller.directStateAccess')
            ->line($finding[1])
            ->build(),
            $findings,
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

    /**
     * Names of `$this` properties whose declared type is a Doctrine persistence collaborator.
     *
     * @return list<string>
     */
    private function persistenceProperties(Class_ $node, Scope $scope): array
    {
        $properties = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && '__construct' === $stmt->name->name) {
                foreach ($stmt->params as $param) {
                    // Promoted constructor properties carry a visibility flag.
                    if (0 !== $param->flags && $param->var instanceof Variable && \is_string($param->var->name)
                        && $this->isPersistenceType($this->resolveTypeName($param->type, $scope))) {
                        $properties[] = $param->var->name;
                    }
                }
            }

            if ($stmt instanceof Property && $this->isPersistenceType($this->resolveTypeName($stmt->type, $scope))) {
                foreach ($stmt->props as $prop) {
                    $properties[] = $prop->name->name;
                }
            }
        }

        return $properties;
    }

    private function resolveTypeName(?Node $type, Scope $scope): ?string
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        return $type instanceof Name ? $scope->resolveName($type) : null;
    }

    private function isPersistenceType(?string $fqcn): bool
    {
        if (null === $fqcn) {
            return false;
        }

        if (\in_array($fqcn, self::PERSISTENCE_TYPES, true)) {
            return true;
        }

        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($fqcn);
        foreach (self::PERSISTENCE_TYPES as $persistenceType) {
            if ($reflection->isSubclassOf($persistenceType) || $reflection->implementsInterface($persistenceType)) {
                return true;
            }
        }

        return false;
    }
}
