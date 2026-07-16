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
    private const array BUILDER_METHODS = ['add', 'create'];

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        $buildForm = array_find($node->getMethods(), fn ($method) => 'buildForm' === $method->name->name);

        if (null === $buildForm) {
            return [];
        }

        $errors = [];
        $this->walkNodes($buildForm->stmts ?? [], $errors);

        return $errors;
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
            $node instanceof Node\Expr\MethodCall
            && $node->name instanceof Node\Identifier
            && \in_array($node->name->name, self::BUILDER_METHODS, true)
        ) {
            foreach ($node->getArgs() as $arg) {
                if ($arg->value instanceof Node\Expr\Array_) {
                    $this->reportConstraintsKeys($arg->value, $errors);
                }
            }
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

    /**
     * Reports 'constraints' keys anywhere inside a builder options array —
     * including nested option arrays such as CollectionType's 'entry_options'.
     *
     * @param list<RuleError> &$errors
     */
    private function reportConstraintsKeys(Node $node, array &$errors): void
    {
        if (
            $node instanceof Node\Expr\ArrayItem
            && $node->key instanceof Node\Scalar\String_
            && 'constraints' === $node->key->value
        ) {
            $errors[] = RuleErrorBuilder::message(
                'Form constraints must be declared on the DTO class (introduce a Request DTO for unmapped forms), not inline in buildForm().',
            )
            ->identifier('form.inlineConstraints')
            ->line($node->getLine())
            ->build();

            return; // do not recurse into this item's value
        }

        if ($node instanceof Node\Expr\Array_) {
            foreach ($node->items as $item) {
                $this->reportConstraintsKeys($item, $errors);
            }

            return;
        }

        if ($node instanceof Node\Expr\ArrayItem && $node->value instanceof Node\Expr\Array_) {
            // Nested option arrays (e.g. 'entry_options' => [...]) carry constraints too.
            $this->reportConstraintsKeys($node->value, $errors);
        }
    }
}
