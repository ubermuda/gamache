<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @implements Rule<Node\Attribute>
 */
final readonly class IsGrantedNoFullyAuthRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Attribute::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Node\Attribute);

        if (IsGranted::class !== $scope->resolveName($node->name)) {
            return [];
        }
        $attrArg = array_find($node->args, fn ($arg) => $arg instanceof Node\Arg && (null === $arg->name || 'attribute' === $arg->name->name));

        if (null === $attrArg || !$attrArg->value instanceof Node\Scalar\String_) {
            return [];
        }

        if ('IS_AUTHENTICATED_FULLY' !== $attrArg->value->value) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                '#[IsGranted(\'IS_AUTHENTICATED_FULLY\')] bypasses Voter-based ownership checks. '
                .'Specify a Voter constant and a subject instead.'
            )
            ->identifier('isGranted.isAuthenticatedFully')
            ->build(),
        ];
    }
}
