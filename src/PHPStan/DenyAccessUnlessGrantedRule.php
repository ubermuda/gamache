<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class DenyAccessUnlessGrantedRule implements Rule
{
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

        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name;

        if ($this->reflectionProvider->hasClass($fqcn)) {
            $reflection = $this->reflectionProvider->getClass($fqcn);
            if (!$reflection->isSubclassOf($this->controllerBaseClass)) {
                return [];
            }
        } else {
            // Reflection not available (e.g. global-namespace fixture): fall back to AST parent check.
            if (null === $node->extends) {
                return [];
            }

            $resolvedParent = $scope->resolveName($node->extends);
            if ($this->controllerBaseClass !== $resolvedParent) {
                return [];
            }
        }

        // Exemption: class-level docblock or comment containing 'access is enforced per-branch'
        $docComment = $node->getDocComment();
        if (null !== $docComment && str_contains($docComment->getText(), 'access is enforced per-branch')) {
            return [];
        }
        foreach ($node->getComments() as $comment) {
            if (str_contains($comment->getText(), 'access is enforced per-branch')) {
                return [];
            }
        }
        $invoke = array_find($node->getMethods(), fn ($method) => '__invoke' === $method->name->name);

        if (null === $invoke || null === $invoke->stmts) {
            return [];
        }

        // Find all denyAccessUnlessGranted() calls anywhere in __invoke body
        $finder = new NodeFinder();
        /** @var Node\Expr\MethodCall[] $calls */
        $calls = $finder->findInstanceOf($invoke->stmts, Node\Expr\MethodCall::class);

        $errors = [];
        foreach ($calls as $call) {
            if (
                $call->var instanceof Node\Expr\Variable
                && 'this' === $call->var->name
                && $call->name instanceof Node\Identifier
                && 'denyAccessUnlessGranted' === $call->name->name
            ) {
                $errors[] = RuleErrorBuilder::message(
                    'AppController::__invoke() must not call $this->denyAccessUnlessGranted(). '
                    .'Use #[IsGranted] with a Voter constant and subject. '
                    .'To exempt dynamic-subject controllers, add "access is enforced per-branch" to the class docblock.'
                )
                ->identifier('controller.denyAccessUnlessGranted')
                ->line($call->getLine())
                ->build();
            }
        }

        return $errors;
    }
}
