<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Node\Attribute>
 *
 * @phpstan-type AttributeSite array{class: string, argumentNames: list<string>}
 */
final readonly class TranslationAttributeRule implements Rule
{
    /**
     * @param list<AttributeSite> $attributeSites
     */
    public function __construct(
        private TranslationKeyValidator $validator,
        private ReflectionProvider $reflectionProvider,
        private array $attributeSites,
    ) {
    }

    public function getNodeType(): string
    {
        return Node\Attribute::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Node\Attribute);

        $attributeClass = $scope->resolveName($node->name);
        $errors = [];

        foreach ($this->attributeSites as $site) {
            if (!$this->isSubclassOf($attributeClass, $site['class'])) {
                continue;
            }

            foreach ($node->args as $position => $arg) {
                if (!$arg instanceof Node\Arg) {
                    continue;
                }

                if ($arg->name instanceof Node\Identifier) {
                    $paramName = $arg->name->name;
                } else {
                    // Positional arg — resolve parameter name from the attribute's constructor.
                    $paramName = $this->getParameterNameAtPosition($attributeClass, $position);
                }

                if (null === $paramName || !\in_array($paramName, $site['argumentNames'], true)) {
                    continue;
                }
                if (!$arg->value instanceof Node\Scalar\String_) {
                    continue;
                }

                $value = $arg->value->value;
                if ($this->validator->isValid($value)) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Attribute argument "%s" of #[%s] must be a translation key (e.g. "account.registration.validator.email_unique"), got "%s".',
                    $paramName,
                    $attributeClass,
                    $value,
                ))
                ->identifier('translation.keyRequired')
                ->build();
            }
        }

        return $errors;
    }

    private function isSubclassOf(string $className, string $parentClass): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $reflection = $this->reflectionProvider->getClass($className);

        return $reflection->getName() === $parentClass
            || $reflection->isSubclassOf($parentClass);
    }

    private function getParameterNameAtPosition(string $className, int $position): ?string
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }
        $reflection = $this->reflectionProvider->getClass($className);
        if (!$reflection->hasConstructor()) {
            return null;
        }
        $variants = $reflection->getConstructor()->getVariants();
        if (!isset($variants[0])) {
            return null;
        }
        $params = $variants[0]->getParameters();

        return isset($params[$position]) ? $params[$position]->getName() : null;
    }
}
