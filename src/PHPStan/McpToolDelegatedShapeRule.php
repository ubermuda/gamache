<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * An MCP tool that hands its work to an injected query or handler must not
 * restate the array shape that handler already declares.
 *
 * A copied shape has no way of staying a copy. Add a key on the handler and the
 * tool's docblock keeps promising the old shape — and it is the tool's docblock
 * a caller reads and a schema generator emits, so the drift ships as a
 * documented lie rather than as an error. Static analysis cannot catch it
 * either: the copy is a supertype of nothing and a subtype of nothing, just a
 * second declaration that used to be right.
 *
 * Only an exact restatement is reported, and only when every return statement
 * delegates through an injected property. A tool that assembles its own array
 * is declaring its own shape, which is the same text for a different reason.
 *
 * @implements Rule<MethodReturnStatementsNode>
 */
final readonly class McpToolDelegatedShapeRule implements Rule
{
    private const string ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const string METHOD = '__invoke';

    public function getNodeType(): string
    {
        return MethodReturnStatementsNode::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof MethodReturnStatementsNode);

        if (self::METHOD !== $node->getMethodName()) {
            return [];
        }

        $class = $node->getClassReflection();
        if (!self::isTool($class)) {
            return [];
        }

        // The phpdoc type specifically: the native `array` is not a
        // restatement of anything, and cannot drift.
        $declared = $node->getMethodReflection()->getPhpDocReturnType();

        if ([] === $declared->getConstantArrays()) {
            return [];
        }

        $returns = $node->getReturnStatements();
        if ([] === $returns) {
            return [];
        }

        $delegate = null;
        $delegated = null;

        foreach ($returns as $return) {
            $expr = $return->getReturnNode()->expr;
            if (null === $expr) {
                return [];
            }

            $called = self::delegateName($expr);
            if (null === $called) {
                return [];
            }

            $type = $return->getScope()->getType($expr);
            if (null !== $delegated && !$type->equals($delegated)) {
                return [];
            }

            $delegate ??= $called;
            $delegated ??= $type;
        }

        \assert($delegated instanceof Type);

        if (!$delegated->equals($declared)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'MCP tool %s::%s() restates the array shape %s already declares. Drop the duplicate so the two cannot drift.',
                $class->getName(),
                self::METHOD,
                $delegate,
            ))
                ->identifier('mcp.duplicatedDelegatedShape')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private static function isTool(ClassReflection $class): bool
    {
        foreach ($class->getNativeReflection()->getAttributes() as $attribute) {
            if (self::ATTRIBUTE === $attribute->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The injected dependency a return statement hands off to, as it reads at
     * the call site. Null when the expression builds a value of its own.
     */
    private static function delegateName(Node\Expr $expr): ?string
    {
        if ($expr instanceof FuncCall && $expr->name instanceof PropertyFetch) {
            $property = self::propertyName($expr->name);

            return null === $property ? null : sprintf('$this->%s', $property);
        }

        if ($expr instanceof MethodCall && $expr->var instanceof PropertyFetch && $expr->name instanceof Node\Identifier) {
            $property = self::propertyName($expr->var);

            return null === $property ? null : sprintf('$this->%s->%s()', $property, $expr->name->name);
        }

        return null;
    }

    private static function propertyName(PropertyFetch $fetch): ?string
    {
        if (!$fetch->var instanceof Node\Expr\Variable || 'this' !== $fetch->var->name) {
            return null;
        }

        return $fetch->name instanceof Node\Identifier ? $fetch->name->name : null;
    }
}
