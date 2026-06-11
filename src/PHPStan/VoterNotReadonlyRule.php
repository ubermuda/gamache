<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @implements Rule<Class_>
 */
final readonly class VoterNotReadonlyRule implements Rule
{
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

        // Only care about readonly classes — non-readonly always pass
        if (!$node->isReadonly()) {
            return [];
        }

        // Resolve FQCN: namespacedName is set by PHPStan's name resolver for namespaced classes;
        // fall back to the bare class name for global-namespace classes.
        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name;

        if ($this->reflectionProvider->hasClass($fqcn)) {
            if (!$this->reflectionProvider->getClass($fqcn)->isSubclassOf(Voter::class)) {
                return [];
            }
        } else {
            // Reflection not available (e.g. global-namespace fixture): fall back to AST parent check.
            if (null === $node->extends) {
                return [];
            }

            $resolvedParent = $scope->resolveName($node->extends);
            if (Voter::class !== $resolvedParent) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Voter %s must not be readonly. Use "final class", not "final readonly class".',
                $node->name->name,
            ))
            ->identifier('voter.isReadonly')
            ->build(),
        ];
    }
}
