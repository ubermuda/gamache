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
    private const array FORBIDDEN_METHODS = ['denyAccessUnlessGranted', 'createAccessDeniedException'];

    private const array ACCESS_DENIED_EXCEPTIONS = [
        'Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException' => 'AccessDeniedHttpException',
        'Symfony\Component\Security\Core\Exception\AccessDeniedException' => 'AccessDeniedException',
    ];

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

        $invoke = array_find($node->getMethods(), fn ($method) => '__invoke' === $method->name->name);

        if (null === $invoke || null === $invoke->stmts) {
            return [];
        }

        // Find all manual access-deny constructs anywhere in __invoke body
        $finder = new NodeFinder();

        /** @var list<array{string, int}> $findings */
        $findings = [];

        /** @var Node\Expr\MethodCall[] $calls */
        $calls = $finder->findInstanceOf($invoke->stmts, Node\Expr\MethodCall::class);
        foreach ($calls as $call) {
            if (
                $call->var instanceof Node\Expr\Variable
                && 'this' === $call->var->name
                && $call->name instanceof Node\Identifier
                && \in_array($call->name->name, self::FORBIDDEN_METHODS, true)
            ) {
                $findings[] = [\sprintf('call $this->%s()', $call->name->name), $call->getLine()];
            }
        }

        /** @var Node\Expr\New_[] $instantiations */
        $instantiations = $finder->findInstanceOf($invoke->stmts, Node\Expr\New_::class);
        foreach ($instantiations as $instantiation) {
            if (!$instantiation->class instanceof Node\Name) {
                continue;
            }

            $resolved = $scope->resolveName($instantiation->class);
            if (\array_key_exists($resolved, self::ACCESS_DENIED_EXCEPTIONS)) {
                $findings[] = [\sprintf('instantiate %s', self::ACCESS_DENIED_EXCEPTIONS[$resolved]), $instantiation->getLine()];
            }
        }

        usort($findings, static fn (array $a, array $b): int => $a[1] <=> $b[1]);

        return array_map(
            static fn (array $finding): RuleError => RuleErrorBuilder::message(
                \sprintf('AppController::__invoke() must not %s. ', $finding[0])
                .'Use #[IsGranted] with a Voter constant and subject. '
                .'If the subject is only resolvable at runtime (e.g. from a query parameter), call denyAccessUnlessGranted() from a private helper method, not __invoke().'
            )
            ->identifier('controller.denyAccessUnlessGranted')
            ->line($finding[1])
            ->build(),
            $findings,
        );
    }
}
