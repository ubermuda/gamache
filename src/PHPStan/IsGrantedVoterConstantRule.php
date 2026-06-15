<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @implements Rule<Attribute>
 */
final readonly class IsGrantedVoterConstantRule implements Rule
{
    /** @param list<string> $allowedAttributePrefixes */
    public function __construct(
        private array $allowedAttributePrefixes,
    ) {
    }

    public function getNodeType(): string
    {
        return Attribute::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Attribute);

        if (IsGranted::class !== $scope->resolveName($node->name)) {
            return [];
        }

        $argument = $this->findAttributeArgument($node);
        if (!$argument instanceof String_) {
            return [];
        }

        foreach ($this->allowedAttributePrefixes as $prefix) {
            if (str_starts_with($argument->value, $prefix)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'The #[IsGranted] attribute \'%s\' must reference a Voter class constant (e.g. EventVoter::EDIT), not a string literal. Framework attributes (%s) are exempt.',
                $argument->value,
                implode(', ', $this->allowedAttributePrefixes),
            ))
            ->identifier('security.isGrantedVoterConstant')
            ->line($argument->getLine())
            ->build(),
        ];
    }

    private function findAttributeArgument(Attribute $attribute): ?Expr
    {
        foreach ($attribute->args as $arg) {
            if (null !== $arg->name && 'attribute' === $arg->name->name) {
                return $arg->value;
            }
        }

        foreach ($attribute->args as $arg) {
            if (null === $arg->name) {
                return $arg->value;
            }
        }

        return null;
    }
}
