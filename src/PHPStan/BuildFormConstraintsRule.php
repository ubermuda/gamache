<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final readonly class BuildFormConstraintsRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        if (!$this->hasDataClass($node)) {
            return [];
        }
        $buildForm = array_find($node->getMethods(), fn ($method) => 'buildForm' === $method->name->name);

        if (null === $buildForm) {
            return [];
        }

        $errors = [];
        $this->walkNodes($buildForm->stmts ?? [], $errors);

        return $errors;
    }

    private function hasDataClass(Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ('configureOptions' !== $method->name->name) {
                continue;
            }

            return $this->methodContainsDataClassKey($method);
        }

        return false;
    }

    private function methodContainsDataClassKey(Node $node): bool
    {
        if (
            $node instanceof Node\Expr\ArrayItem
            && $node->key instanceof Node\Scalar\String_
            && 'data_class' === $node->key->value
        ) {
            return true;
        }

        foreach ($node->getSubNodeNames() as $subName) {
            $sub = $node->{$subName};
            if ($sub instanceof Node && $this->methodContainsDataClassKey($sub)) {
                return true;
            } elseif (\is_array($sub)) {
                foreach ($sub as $child) {
                    if ($child instanceof Node && $this->methodContainsDataClassKey($child)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<Node\Stmt> $stmts
     * @param list<RuleError>  &$errors
     */
    private function walkNodes(array $stmts, array &$errors): void
    {
        foreach ($stmts as $stmt) {
            $this->walkNode($stmt, $errors);
        }
    }

    /** @param list<RuleError> &$errors */
    private function walkNode(Node $node, array &$errors): void
    {
        if (
            $node instanceof Node\Expr\ArrayItem
            && $node->key instanceof Node\Scalar\String_
            && 'constraints' === $node->key->value
        ) {
            $errors[] = RuleErrorBuilder::message(
                'Form constraints must be declared on the DTO class, not inline in buildForm().',
            )
            ->identifier('form.inlineConstraints')
            ->line($node->getLine())
            ->build();

            return; // do not recurse into this item's value
        }

        foreach ($node->getSubNodeNames() as $subName) {
            $sub = $node->{$subName};
            if ($sub instanceof Node) {
                $this->walkNode($sub, $errors);
            } elseif (\is_array($sub)) {
                foreach ($sub as $child) {
                    if ($child instanceof Node) {
                        $this->walkNode($child, $errors);
                    }
                }
            }
        }
    }
}
