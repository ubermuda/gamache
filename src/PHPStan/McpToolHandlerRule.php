<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
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

    private InjectedHandlerFinder $finder;

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
        $this->finder = new InjectedHandlerFinder();
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

        if ($this->finder->injects($node, $scope) || $this->inheritsHandler($node, $scope)) {
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
     * The check is shallow on purpose, here and in the subclass's own
     * constructor. Neither asks whether initialisation happens on every path,
     * so a conditional assignment or a conditional `parent::__construct()`
     * counts. Deciding that needs control-flow analysis, and the shape it would
     * catch leaves a typed property unset, which PHP makes fatal on first read. It does not trace which constructor in a
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
            // A static property belongs to the class, so it injects nothing.
            if ($property->isStatic()) {
                continue;
            }

            foreach (self::reflectionTypeNames($property->getType()) as $name) {
                if (str_ends_with($name, InjectedHandlerFinder::SUFFIX)) {
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
        $constructor = InjectedHandlerFinder::constructor($class);
        if (null === $constructor) {
            return true;
        }

        foreach (InjectedHandlerFinder::executedNodes($constructor) as $node) {
            if ($node instanceof StaticCall
                && $node->class instanceof Name && 'parent' === $node->class->toLowerString()
                && $node->name instanceof Identifier && '__construct' === $node->name->toLowerString()) {
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
            return $type->isBuiltin() ? [] : [InjectedHandlerFinder::shortName($type->getName())];
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
}
