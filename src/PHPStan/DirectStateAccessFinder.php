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

/**
 * Finds the calls a class makes on an injected Doctrine persistence
 * collaborator: the EntityManager/ObjectManager, the DBAL Connection, or a
 * repository.
 *
 * A handler is invoked as a callable (`($this->handler)(...)`), which is a
 * FuncCall rather than a MethodCall, so delegation is never a finding.
 * Inherited helpers (`$this->getUser()`) are called on `$this` rather than on a
 * persistence collaborator, so they are not findings either.
 *
 * The rules that consume this decide which classes to ask about, and what to
 * say about the answer.
 */
final readonly class DirectStateAccessFinder
{
    private const array PERSISTENCE_TYPES = [
        'Doctrine\Persistence\ObjectManager',
        'Doctrine\Persistence\ObjectRepository',
        'Doctrine\DBAL\Connection',
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * The called method names and their lines, in source order.
     *
     * @return list<array{string, int}>
     */
    public function findings(Class_ $node, Scope $scope): array
    {
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

        return $findings;
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
