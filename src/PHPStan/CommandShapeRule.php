<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class CommandShapeRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        if (null === $node->name || null === $node->namespacedName) {
            return [];
        }

        $fqcn = $node->namespacedName->toString();
        if (!preg_match('/\\\\Command\\\\/', $fqcn) || str_ends_with($fqcn, 'Handler')) {
            return [];
        }

        // CQRS commands are plain data objects — they never extend a parent.
        // Classes that do extend something (e.g. Symfony console commands that extend
        // Symfony\Component\Console\Command\Command) live in a Command\ namespace but
        // are not CQRS commands; skip them to avoid false positives.
        if (null !== $node->extends) {
            return [];
        }

        $errors = [];

        if (!$node->isFinal() || !$node->isReadonly()) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Command %s must be declared as "final readonly class".',
                $node->name->name,
            ))
            ->identifier('command.notFinalReadonly')
            ->line($node->getLine())
            ->build();
        }

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->isPublic() && '__construct' !== $stmt->name->name) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Command %s must not declare public methods other than __construct().',
                    $node->name->name,
                ))
                ->identifier('command.hasPublicMethods')
                ->line($node->getLine())
                ->build();
                break;
            }
        }

        return $errors;
    }
}
