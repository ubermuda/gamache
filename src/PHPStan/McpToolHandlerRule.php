<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * An MCP tool must inject a handler, so that the work it exposes has a name and
 * a home outside the transport.
 *
 * A tool is one of two front doors onto the same domain, and the HTTP one
 * already goes through a Command/Handler. A tool that does the work itself puts
 * a second copy of a rule behind an agent, where nobody looks: the web form
 * rejects an empty title and the tool accepts one, and both are the product.
 * The handler is also the only layer with a test that does not have to speak
 * MCP.
 *
 * The rule asks for a constructor parameter whose type name ends in `Handler`,
 * which is the shape a Command/Handler pair takes. It cannot ask whether the
 * handler is the one that does the work, so a tool that injects a handler and
 * still reaches for a repository passes here and is reported by
 * McpToolNoDirectStateAccessRule instead. The two rules answer different
 * halves of the same convention.
 *
 * @implements Rule<Class_>
 */
final readonly class McpToolHandlerRule implements Rule
{
    private const string ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const string SUFFIX = 'Handler';

    public function __construct(
        private ReflectionProvider $reflectionProvider,
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

        $attribute = $this->findAttribute($node, $scope);
        if (null === $attribute) {
            return [];
        }

        if ($this->injectsHandler($node, $scope) || $this->inheritsHandler($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'MCP tool %s injects no handler; give it a Command/Handler pair to delegate to.',
                $node->name->name,
            ))
                ->identifier('mcp.missingHandler')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function findAttribute(Class_ $class, Scope $scope): ?Node\Attribute
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (self::ATTRIBUTE === $scope->resolveName($attribute->name)) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * A constructor parameter typed as something named `*Handler` that the tool
     * keeps. Promotion is not required, since a constructor that assigns the
     * parameter by hand injects it just the same — but a parameter that is
     * neither promoted nor assigned is gone by the time `__invoke()` runs, so
     * the tool has nothing to delegate to.
     */
    private function injectsHandler(Class_ $class, Scope $scope): bool
    {
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod || '__construct' !== $stmt->name->name) {
                continue;
            }

            foreach ($stmt->params as $param) {
                if (!$param->var instanceof Variable || !\is_string($param->var->name)) {
                    continue;
                }

                foreach (self::typeNames($param->type, $scope) as $name) {
                    if (!str_ends_with($name, self::SUFFIX)) {
                        continue;
                    }

                    // Promoted constructor properties carry a visibility flag.
                    if (0 !== $param->flags || self::isKept($stmt, $param->var->name)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether a parent class holds the handler on the tool's behalf. A base
     * class that takes the handler and a subclass that only declares the
     * attribute is one class between them, and reporting the subclass would be
     * reporting a tool that delegates.
     *
     * The question asked of the parent is what the instance retains, not what
     * its constructor accepts: a property typed `*Handler`, promoted or
     * assigned. A parent that takes a handler and drops it leaves the subclass
     * with nothing, exactly as it would in the subclass's own constructor.
     *
     * The parent is read through reflection, since it is not in the file being
     * analysed. The subclass is not, because a rule that needed reflection for
     * the class it is given could not run before the analysed file is
     * autoloadable.
     *
     * The check is shallow on purpose. It does not trace which constructor in a
     * longer chain sets the property, so a parent that declares a handler
     * property and never assigns it clears the subclass. Such a parent is a
     * fatal error on first use, since a typed property cannot be read before it
     * is initialised, and a lint rule is the wrong place to catch it.
     */
    private function inheritsHandler(Class_ $class, Scope $scope): bool
    {
        if (null === $class->extends || !self::runsParentConstructor($class)) {
            return false;
        }

        $parent = $scope->resolveName($class->extends);
        if (!$this->reflectionProvider->hasClass($parent)) {
            return false;
        }

        // Native properties include the ones the parent inherits in turn.
        foreach ($this->reflectionProvider->getClass($parent)->getNativeReflection()->getProperties() as $property) {
            foreach (self::reflectionTypeNames($property->getType()) as $name) {
                if (str_ends_with($name, self::SUFFIX)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the parent's constructor runs at all. A subclass that declares
     * its own overrides it, and the parent's properties stay unset unless the
     * override calls `parent::__construct()`.
     */
    private static function runsParentConstructor(Class_ $class): bool
    {
        $constructor = null;
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && '__construct' === $stmt->name->name) {
                $constructor = $stmt;
            }
        }

        if (null === $constructor) {
            return true;
        }

        /** @var StaticCall[] $calls */
        $calls = new NodeFinder()->findInstanceOf($constructor->stmts ?? [], StaticCall::class);

        foreach ($calls as $call) {
            if ($call->class instanceof Name && 'parent' === $call->class->toLowerString()
                && $call->name instanceof Identifier && '__construct' === $call->name->toLowerString()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The class names a reflected type resolves to, each shortened to its last
     * segment. A builtin contributes nothing.
     *
     * @return list<string>
     */
    private static function reflectionTypeNames(?\ReflectionType $type): array
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [self::shortName($type->getName())];
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $branch) {
                $names = [...$names, ...self::reflectionTypeNames($branch)];
            }

            return $names;
        }

        return [];
    }

    /** Whether the constructor assigns the named parameter to a property. */
    private static function isKept(ClassMethod $constructor, string $parameter): bool
    {
        /** @var Assign[] $assignments */
        $assignments = new NodeFinder()->findInstanceOf($constructor->stmts ?? [], Assign::class);

        foreach ($assignments as $assignment) {
            if ($assignment->var instanceof PropertyFetch
                && $assignment->var->var instanceof Variable
                && 'this' === $assignment->var->var->name
                && $assignment->expr instanceof Variable
                && $parameter === $assignment->expr->name) {
                return true;
            }
        }

        return false;
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

    private static function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return false === $position ? $fqcn : substr($fqcn, $position + 1);
    }
}
