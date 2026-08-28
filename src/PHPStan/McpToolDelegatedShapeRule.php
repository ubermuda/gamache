<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodReturnStatementsNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * An MCP tool that hands its work to an injected query or handler must not
 * restate the array shape that handler already declares.
 *
 * The shape is then written down twice, and the two copies have to be edited in
 * lockstep. No client ever reads the second one — the SDK emits an
 * `outputSchema` only when the `McpTool` attribute supplies one, so the tool's
 * `@return` is read by developers and by static analysis, not by callers — but
 * that is exactly who pays for it: add a key to the handler and PHPStan reports
 * the mismatch against the tool, pointing at the copy rather than at the change
 * that broke it.
 *
 * Importing the handler's alias removes the second declaration, so a `@return`
 * naming an alias imported from the delegate's own class is accepted. A literal
 * shape is reported, and so is an alias the tool defines for itself or imports
 * from a third class: both are declarations of their own, free to drift from
 * the handler's.
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

    /** In the order PHPStan itself prefers them. */
    private const array RETURN_TAGS = ['@phpstan-return', '@psalm-return', '@return'];

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
        $delegateClasses = [];

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
            $delegateClasses = [...$delegateClasses, ...self::delegateClasses($expr, $return->getScope())];
        }

        \assert($delegated instanceof Type);

        if (!$delegated->equals($declared)) {
            return [];
        }

        if (self::importsDelegateAlias($class, $delegateClasses)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'MCP tool %s::%s() restates the array shape %s already declares. Import that shape with @phpstan-import-type instead of restating it.',
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
     * Whether the `@return` is written as an alias the class imports from one
     * of the classes it delegates to. The resolved type cannot answer this: an
     * imported alias and a copy of what it stands for are the same type.
     *
     * @param list<string> $delegateClasses
     */
    private static function importsDelegateAlias(ClassReflection $class, array $delegateClasses): bool
    {
        $alias = self::returnedAliasName($class);
        if (null === $alias) {
            return false;
        }

        $phpDoc = $class->getResolvedPhpDoc();
        if (null === $phpDoc) {
            return false;
        }

        $import = $phpDoc->getTypeAliasImportTags()[$alias] ?? null;
        if (null === $import) {
            return false;
        }

        foreach ($delegateClasses as $delegateClass) {
            if (0 === strcasecmp($delegateClass, $import->getImportedFrom())) {
                return true;
            }
        }

        return false;
    }

    /** The name the `@return` is written with, when it is a bare identifier. */
    private static function returnedAliasName(ClassReflection $class): ?string
    {
        $phpDoc = $class->getNativeMethod(self::METHOD)->getResolvedPhpDoc();
        if (null === $phpDoc) {
            return null;
        }

        foreach (self::RETURN_TAGS as $tag) {
            foreach ($phpDoc->getPhpDocNodes() as $docNode) {
                foreach ($docNode->getReturnTagValues($tag) as $value) {
                    return $value->type instanceof IdentifierTypeNode ? $value->type->name : null;
                }
            }
        }

        return null;
    }

    /**
     * The classes a delegating return statement hands off to.
     *
     * @return list<string>
     */
    private static function delegateClasses(Node\Expr $expr, Scope $scope): array
    {
        $target = match (true) {
            $expr instanceof FuncCall && $expr->name instanceof PropertyFetch => $expr->name,
            $expr instanceof MethodCall => $expr->var,
            default => null,
        };

        return null === $target ? [] : $scope->getType($target)->getObjectClassNames();
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
