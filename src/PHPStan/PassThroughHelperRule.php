<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A private/protected method whose entire body forwards its parameters,
 * unchanged and in order, to a single call on a constructor-promoted
 * dependency adds indirection with no logic — inline the call at its
 * call sites.
 *
 * Deliberately narrow to avoid false positives. It only fires when:
 * - the method is private or protected, non-static, non-abstract;
 * - the body is exactly one statement: `return $this->dep->call(...);`
 *   or a lone `$this->dep->call(...);` expression;
 * - the receiver is a constructor-promoted property;
 * - the arguments are plain variables matching the method's parameter
 *   list exactly (same names, same order, no extras, no named arguments,
 *   no spread, no by-ref or variadic parameters).
 *
 * Helpers that add real logic — argument shaping, conditionals, loops,
 * extra arguments, multiple statements — never match. A protected method in
 * a class that extends a parent is skipped entirely: it may override or
 * implement a parent contract, and a contract method cannot be inlined at
 * its call sites.
 *
 * @implements Rule<InClassNode>
 */
final readonly class PassThroughHelperRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof InClassNode);

        $class = $node->getOriginalNode();
        if (!$class instanceof Class_ || null === $class->name) {
            return [];
        }

        $promoted = $this->promotedPropertyNames($class);
        if ([] === $promoted) {
            return [];
        }

        $errors = [];
        foreach ($class->getMethods() as $method) {
            if (!$method->isPrivate() && !$method->isProtected()) {
                continue;
            }
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }

            $call = $this->passThroughCall($method, $promoted);
            if (null === $call) {
                continue;
            }

            if ($method->isProtected() && null !== $class->extends) {
                // A protected method in a subclass may override or implement a
                // parent contract — skipping all of them keeps the rule quiet.
                continue;
            }

            \assert($call->var instanceof PropertyFetch && $call->var->name instanceof Identifier);
            \assert($call->name instanceof Identifier);

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Method %s::%s() is a one-liner pass-through to $this->%s->%s() — inline the call at its call sites.',
                $class->name->name,
                $method->name->name,
                $call->var->name->name,
                $call->name->name,
            ))
            ->identifier('method.passThroughHelper')
            ->line($method->getLine())
            ->build();
        }

        return $errors;
    }

    /** @return list<string> */
    private function promotedPropertyNames(Class_ $class): array
    {
        $constructor = array_find($class->getMethods(), fn ($method) => '__construct' === $method->name->name);
        if (null === $constructor) {
            return [];
        }

        $names = [];
        foreach ($constructor->params as $param) {
            if (0 !== $param->flags && $param->var instanceof Variable && \is_string($param->var->name)) {
                $names[] = $param->var->name;
            }
        }

        return $names;
    }

    /**
     * Returns the forwarded call when the method matches the pass-through
     * shape, null otherwise.
     *
     * @param list<string> $promoted
     */
    private function passThroughCall(ClassMethod $method, array $promoted): ?MethodCall
    {
        $statements = $method->stmts;
        if (null === $statements || 1 !== \count($statements)) {
            return null;
        }

        $statement = $statements[0];
        if ($statement instanceof Node\Stmt\Return_) {
            $call = $statement->expr;
        } elseif ($statement instanceof Node\Stmt\Expression) {
            $call = $statement->expr;
        } else {
            return null;
        }

        if (!$call instanceof MethodCall || !$call->name instanceof Identifier) {
            return null;
        }

        $receiver = $call->var;
        if (
            !$receiver instanceof PropertyFetch
            || !$receiver->var instanceof Variable
            || 'this' !== $receiver->var->name
            || !$receiver->name instanceof Identifier
            || !\in_array($receiver->name->name, $promoted, true)
        ) {
            return null;
        }

        return $this->forwardsParametersUnchanged($method, $call) ? $call : null;
    }

    private function forwardsParametersUnchanged(ClassMethod $method, MethodCall $call): bool
    {
        $parameterNames = [];
        foreach ($method->params as $param) {
            if ($param->variadic || $param->byRef || 0 !== $param->flags) {
                return false; // variadics, by-ref, promotion: not a plain forward
            }
            if (!$param->var instanceof Variable || !\is_string($param->var->name)) {
                return false;
            }
            $parameterNames[] = $param->var->name;
        }

        $argumentNames = [];
        foreach ($call->args as $arg) {
            if (!$arg instanceof Node\Arg || null !== $arg->name || $arg->unpack) {
                return false; // named arguments or spread: caller-visible shaping
            }
            if (!$arg->value instanceof Variable || !\is_string($arg->value->name)) {
                return false; // anything but a plain variable is a transformation
            }
            $argumentNames[] = $arg->value->name;
        }

        return $argumentNames === $parameterNames;
    }

}
