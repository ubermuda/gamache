<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
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

        if ($this->injectsHandler($node)) {
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
     * A constructor parameter typed as something named `*Handler`. Promotion is
     * not required: a constructor that assigns the parameter by hand injects it
     * just the same.
     */
    private function injectsHandler(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod || '__construct' !== $stmt->name->name) {
                continue;
            }

            foreach ($stmt->params as $param) {
                if (!$param->var instanceof Variable) {
                    continue;
                }

                foreach (self::typeNames($param->type) as $name) {
                    if (str_ends_with($name, self::SUFFIX)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The short names a parameter type is written with. A union or intersection
     * contributes every branch, since any one of them can be the handler.
     *
     * @return list<string>
     */
    private static function typeNames(?Node $type): array
    {
        if ($type instanceof NullableType) {
            return self::typeNames($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            $names = [];
            foreach ($type->types as $branch) {
                $names = [...$names, ...self::typeNames($branch)];
            }

            return $names;
        }

        return $type instanceof Name ? [$type->getLast()] : [];
    }
}
