<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A JSON API surface keeps three signals in lock-step: a route path under
 * "/api/", a route name prefixed "api_", and a class in a "\Controller\Api\"
 * namespace. The path is the canonical signal; the name and namespace are
 * derived from it. Any disagreement (e.g. a misplaced controller, or a web
 * route accidentally named "api_") is reported.
 *
 * @implements Rule<Class_>
 */
final readonly class ApiRouteConsistencyRule implements Rule
{
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

        $namespaceIsApi = str_contains($fqcn, '\\Controller\\Api\\');

        $errors = [];
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if (Route::class !== $scope->resolveName($attr->name)) {
                    continue;
                }

                $path = $this->stringArg($attr, ['path', 'value'], true);
                if (null === $path) {
                    continue;
                }
                $name = $this->stringArg($attr, ['name'], false);

                $pathIsApi = '/api' === $path || str_starts_with($path, '/api/');

                // Compare only the signals that are present. Path and namespace
                // are always available; the name is compared only when set.
                $signals = [$pathIsApi, $namespaceIsApi];
                if (null !== $name) {
                    $signals[] = str_starts_with($name, 'api_');
                }

                $allApi = !\in_array(false, $signals, true);
                $noneApi = !\in_array(true, $signals, true);
                if ($allApi || $noneApi) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(sprintf(
                    'API routing convention mismatch on %s: path "%s", name %s, and namespace %s must agree. An "/api/" path requires an "api_" route name and a "\\Controller\\Api\\" namespace (and vice versa).',
                    $node->name->name,
                    $path,
                    null !== $name ? sprintf('"%s"', $name) : '(none)',
                    $namespaceIsApi ? 'Controller\\Api' : 'non-API',
                ))
                    ->identifier('route.apiConsistency')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $names
     */
    private function stringArg(Node\Attribute $attr, array $names, bool $allowPositional): ?string
    {
        foreach ($attr->args as $arg) {
            if (!$arg instanceof Node\Arg) {
                continue;
            }

            $matches = (null === $arg->name && $allowPositional)
                || (null !== $arg->name && \in_array($arg->name->name, $names, true));

            if ($matches && $arg->value instanceof Node\Scalar\String_) {
                return $arg->value->value;
            }
        }

        return null;
    }
}
