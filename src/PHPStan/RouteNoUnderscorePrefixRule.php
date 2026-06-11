<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @implements Rule<Node\Attribute>
 */
final readonly class RouteNoUnderscorePrefixRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Attribute::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Node\Attribute);

        if (Route::class !== $scope->resolveName($node->name)) {
            return [];
        }

        // Extract path arg: first positional, or named 'path' or 'value'
        $pathValue = null;
        foreach ($node->args as $arg) {
            if ($arg instanceof Node\Arg) {
                $isPath = null === $arg->name
                    || \in_array($arg->name->name, ['path', 'value'], true);
                if ($isPath && $arg->value instanceof Node\Scalar\String_) {
                    $pathValue = $arg->value->value;
                    break;
                }
            }
        }

        if (null === $pathValue) {
            return [];
        }

        if (!str_starts_with($pathValue, '/_')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Route path "%s" must not begin with "/_". That prefix is reserved for Symfony internals.',
                $pathValue,
            ))
            ->identifier('route.underscorePrefix')
            ->build(),
        ];
    }
}
