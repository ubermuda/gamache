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
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @implements Rule<Class_>
 */
final readonly class NotBlankNullableRule implements Rule
{
    public function __construct()
    {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);
        $constructor = array_find($node->stmts, fn ($stmt) => $stmt instanceof ClassMethod && '__construct' === $stmt->name->name);

        if (null === $constructor) {
            return [];
        }

        $errors = [];
        foreach ($constructor->params as $param) {
            if (0 === $param->flags) {
                continue; // not a promoted property
            }

            $hasNotBlank = false;
            foreach ($param->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    if (NotBlank::class === $scope->resolveName($attr->name)) {
                        $hasNotBlank = true;
                        break 2;
                    }
                }
            }

            if (!$hasNotBlank) {
                continue;
            }

            $varName = $param->var instanceof Node\Expr\Variable ? $param->var->name : null;
            $paramName = is_string($varName) ? $varName : '?';

            if (!$this->isNullable($param->type)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Promoted property $%s has #[NotBlank] but is not nullable. Use ?string or string|null.',
                    $paramName,
                ))
                ->identifier('dto.notBlankNotNullable')
                ->line($param->getLine())
                ->build();

                continue;
            }

            if (null !== $param->default && !$this->isNullLiteral($param->default)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Promoted property $%s has #[NotBlank] and is nullable but defaults to a non-null value. Default it to null (or omit the default) so "absent" is not conflated with "empty".',
                    $paramName,
                ))
                ->identifier('dto.notBlankDefaultNotNull')
                ->line($param->getLine())
                ->build();
            }
        }

        return $errors;
    }

    private function isNullLiteral(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Expr\ConstFetch
            && 'null' === strtolower($expr->name->toString());
    }

    private function isNullable(Node\ComplexType|Node\Identifier|Node\Name|null $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $t) {
                if ($t instanceof Node\Identifier && 'null' === $t->name) {
                    return true;
                }
            }
        }

        return false;
    }
}
