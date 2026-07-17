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
 * A private/protected method whose entire body is a single call on one of the
 * class's properties, with only trivially-forwardable arguments, adds
 * indirection with no logic — inline the call at its call sites.
 *
 * The discriminator is "does the body compute anything?", so the facade shape
 * is flagged regardless of cosmetics:
 * - the receiver may be any chain rooted in `$this` — property fetches
 *   (`$this->a->b`), accessor calls (`$this->service->inner()`), or nothing
 *   at all (`$this->siblingMethod(...)` is a pure alias);
 * - arguments (of every call in the chain) may be the method's parameters in
 *   any order (reordered, dropped), property fetches (`$this->x`), named
 *   arguments, or a spread of a parameter (`...$items`) — every one of them
 *   is available verbatim at the call site, so inlining is a copy of the body.
 *
 * Not flagged (the body adds something):
 * - any expression in argument position — calls, arithmetic, concatenation,
 *   array literals, closures — is argument shaping;
 * - literal/constant arguments — binding a value is partial application and
 *   names a variant (`renderCompact()` vs `render(true)`);
 * - multi-statement bodies, conditionals, public methods (a deliberate API
 *   surface), static helpers, by-ref parameters;
 * - a protected method in a class that extends a parent: it may override or
 *   implement a parent contract, and a contract method cannot be inlined.
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

        $errors = [];
        foreach ($class->getMethods() as $method) {
            if (!$method->isPrivate() && !$method->isProtected()) {
                continue;
            }
            if ($method->isStatic() || $method->isAbstract()) {
                continue;
            }

            $call = $this->passThroughCall($method);
            if (null === $call) {
                continue;
            }

            if ($method->isProtected() && null !== $class->extends) {
                // A protected method in a subclass may override or implement a
                // parent contract — skipping all of them keeps the rule quiet.
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Method %s::%s() is a one-liner pass-through to %s — inline the call at its call sites.',
                $class->name->name,
                $method->name->name,
                $this->chainToString($call),
            ))
            ->identifier('method.passThroughHelper')
            ->line($method->getLine())
            ->build();
        }

        return $errors;
    }

    /**
     * Returns the forwarded call when the method matches the pass-through
     * shape, null otherwise.
     */
    private function passThroughCall(ClassMethod $method): ?MethodCall
    {
        foreach ($method->params as $param) {
            if ($param->byRef) {
                return null; // by-ref forwarding is semantics-sensitive
            }
        }

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

        if (!$call instanceof MethodCall || !$this->isPureForwardingChain($call)) {
            return null;
        }

        return $call;
    }

    /**
     * A chain rooted in $this whose every link is a property fetch or a
     * method call with only forwardable arguments: `$this->a->b->m($x)`,
     * `$this->service->inner()->build($x)`, or a bare sibling alias
     * `$this->m($x)`. A non-forwardable argument anywhere in the chain means
     * the body adds something.
     */
    private function isPureForwardingChain(Node\Expr $expr): bool
    {
        while (true) {
            if ($expr instanceof MethodCall) {
                if (!$expr->name instanceof Identifier) {
                    return false;
                }
                foreach ($expr->args as $arg) {
                    if (!$arg instanceof Node\Arg || !$this->isForwardable($arg->value)) {
                        return false;
                    }
                }
                $expr = $expr->var;
            } elseif ($expr instanceof PropertyFetch) {
                if (!$expr->name instanceof Identifier) {
                    return false;
                }
                $expr = $expr->var;
            } else {
                return $expr instanceof Variable && 'this' === $expr->name;
            }
        }
    }

    /**
     * A static property path rooted in $this: `$this->prop`, `$this->a->b`.
     * Unlike the receiver chain, argument paths must not contain calls — a
     * call in argument position transforms the input (argument shaping).
     */
    private function isThisPropertyPath(Node\Expr $expr): bool
    {
        $hops = 0;
        while ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
            $expr = $expr->var;
            ++$hops;
        }

        return $hops > 0 && $expr instanceof Variable && 'this' === $expr->name;
    }

    /**
     * An argument is forwardable when it is available verbatim at the call
     * site: a plain variable (a parameter — reordered or dropped, still no
     * logic) or a $this-rooted property path. Anything else — literals
     * (partial application), calls, expressions — means the body adds
     * something.
     */
    private function isForwardable(Node\Expr $value): bool
    {
        if ($value instanceof Variable && \is_string($value->name)) {
            return true;
        }

        return $value instanceof PropertyFetch && $this->isThisPropertyPath($value);
    }

    private function chainToString(MethodCall $call): string
    {
        $parts = [];
        $expr = $call;
        while (true) {
            if ($expr instanceof MethodCall && $expr->name instanceof Identifier) {
                array_unshift($parts, $expr->name->name.'()');
                $expr = $expr->var;
            } elseif ($expr instanceof PropertyFetch && $expr->name instanceof Identifier) {
                array_unshift($parts, $expr->name->name);
                $expr = $expr->var;
            } else {
                break;
            }
        }

        return '$this->'.implode('->', $parts);
    }
}
