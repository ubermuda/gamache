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
final readonly class RouteParamCamelCaseRule implements Rule
{
    private const string CAMEL_CASE = '/^[a-z][a-zA-Z0-9]*$/';

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

        // Find the path argument: first positional arg or named 'path'/'value'
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

        preg_match_all('/\{([^}]+)\}/', $pathValue, $matches);

        $errors = [];
        foreach ($matches[1] as $raw) {
            // Strip Symfony mapped-param suffix: {id:org} → id
            $name = str_contains($raw, ':')
                ? substr($raw, 0, (int) \strpos($raw, ':'))
                : $raw;
            // Strip regex constraint suffix: {id<\d+>} → id
            if (str_contains($name, '<')) {
                $name = substr($name, 0, (int) \strpos($name, '<'));
            }

            if (!preg_match(self::CAMEL_CASE, $name)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Route parameter "%s" must be camelCase.',
                    $name,
                ))
                ->identifier('route.paramNotCamelCase')
                ->line($node->getLine())
                ->build();
            }
        }

        return $errors;
    }
}
