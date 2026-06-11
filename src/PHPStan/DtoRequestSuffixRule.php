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
use Symfony\Component\Form\AbstractType;

/**
 * @implements Rule<Class_>
 */
final readonly class DtoRequestSuffixRule implements Rule
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

        // Skip anonymous classes
        if (null === $node->name) {
            return [];
        }

        // Skip abstract classes
        if ($node->isAbstract()) {
            return [];
        }

        $fqcn = null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name;

        // Only care about classes in a Form namespace segment
        if (!str_contains($fqcn, '\\Form\\')) {
            return [];
        }

        if (!$this->reflectionProvider->hasClass($fqcn)) {
            return [];
        }

        $reflection = $this->reflectionProvider->getClass($fqcn);

        // Form types (AbstractType subclasses) are not DTOs — skip
        if ($reflection->isSubclassOf(AbstractType::class)) {
            return [];
        }

        // Class name must end with 'Request'
        if (str_ends_with($node->name->name, 'Request')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'DTO class %s in a Form/ namespace must be named with a "Request" suffix (e.g. %sRequest).',
                $node->name->name,
                $node->name->name,
            ))
            ->identifier('dto.requestSuffix')
            ->build(),
        ];
    }
}
