<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;

/**
 * Answers whether a class keeps a handler its own constructor takes: a
 * parameter typed as something named `*Handler`, promoted or assigned.
 *
 * Promotion is not required, since a constructor that assigns the parameter by
 * hand injects it just the same. A parameter that is neither promoted nor
 * assigned is gone by the time the class runs, so it leaves nothing to
 * delegate to.
 *
 * The finder reads the class it is given and nothing above it. A rule that
 * wants to follow an inherited handler reads the parent itself, through
 * reflection.
 *
 * The rules that consume this decide which classes to ask about, and what to
 * say about the answer.
 */
final readonly class InjectedHandlerFinder
{
    public const string SUFFIX = 'Handler';

    public function injects(Class_ $class, Scope $scope): bool
    {
        $constructor = self::constructor($class);
        if (null === $constructor) {
            return false;
        }

        foreach ($constructor->params as $param) {
            if (!$param->var instanceof Variable || !\is_string($param->var->name)) {
                continue;
            }

            foreach (self::typeNames($param->type, $scope) as $name) {
                if (!str_ends_with($name, self::SUFFIX)) {
                    continue;
                }

                // Promoted constructor properties carry a visibility flag.
                if (0 !== $param->flags || self::isKept($constructor, $param->var->name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The constructor the class declares itself, if it declares one. */
    public static function constructor(Class_ $class): ?ClassMethod
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && '__construct' === $stmt->name->name) {
                return $stmt;
            }
        }

        return null;
    }

    /**
     * Every node the constructor body runs itself, with nested function scopes
     * pruned. A closure the constructor builds and does not call runs later or
     * never, so an assignment or a `parent::__construct()` inside one has not
     * happened when the constructor returns.
     *
     * @return list<Node>
     */
    public static function executedNodes(ClassMethod $constructor): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /** @var list<Node> */
            public array $found = [];

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                $this->found[] = $node;

                return null;
            }
        };

        new NodeTraverser($visitor)->traverse($constructor->stmts ?? []);

        return $visitor->found;
    }

    public static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return false === $position ? $fqcn : substr($fqcn, $position + 1);
    }

    /**
     * The class names a parameter type resolves to, each shortened to its last
     * segment. Resolution matters both ways: an import aliased to something
     * else still names the handler class, and a name aliased *to* `*Handler`
     * names whatever class it was imported from.
     *
     * A union or intersection contributes every branch, since any one of them
     * can be the handler.
     *
     * @return list<string>
     */
    private static function typeNames(?Node $type, Scope $scope): array
    {
        if ($type instanceof NullableType) {
            return self::typeNames($type->type, $scope);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            $names = [];
            foreach ($type->types as $branch) {
                $names = [...$names, ...self::typeNames($branch, $scope)];
            }

            return $names;
        }

        if (!$type instanceof Name) {
            return [];
        }

        return [self::shortName($scope->resolveName($type))];
    }

    /** Whether the constructor assigns the named parameter to a property. */
    private static function isKept(ClassMethod $constructor, string $parameter): bool
    {
        foreach (self::executedNodes($constructor) as $node) {
            if ($node instanceof Assign
                && $node->var instanceof PropertyFetch
                && $node->var->var instanceof Variable
                && 'this' === $node->var->var->name
                && $node->expr instanceof Variable
                && $parameter === $node->expr->name) {
                return true;
            }
        }

        return false;
    }
}
