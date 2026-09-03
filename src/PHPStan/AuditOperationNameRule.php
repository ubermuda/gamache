<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * An audit operation name is `<module>.<outcome>`: exactly two snake_case
 * segments, one dot between them.
 *
 * The name is the only axis an audit trail is filtered on. Let two shapes
 * coexist and a reader who filters by prefix has to know which shape each
 * module picked, so `billing.` returns some of billing and `billing.webhook.`
 * returns the rest. Nothing else notices, because the column is a string and
 * every shape writes and reads back cleanly.
 *
 * Two things the rule does not see, both deliberate.
 *
 * A first argument that is not a string literal passes. A handler that routes
 * the name through its own private `record()` helper leaves the literal one
 * hop away, at the helper's call site. Following that hop is real complexity
 * for a handful of callers, and the literal is still plain to a reviewer
 * reading `$this->record('billing.comp_granted', ...)`. The cost is a
 * malformed name that re-enters through one of those helpers.
 *
 * A name passed as a named argument passes for the same reason: the rule reads
 * the first positional argument only.
 *
 * @implements Rule<MethodCall>
 */
final readonly class AuditOperationNameRule implements Rule
{
    private const string PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    /**
     * @param list<string> $recordMethods
     */
    public function __construct(
        private string $auditorClass,
        private array $recordMethods,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof MethodCall);

        if ('' === $this->auditorClass) {
            return [];
        }

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        if (!\in_array($node->name->name, $this->recordMethods, true)) {
            return [];
        }

        if (!new ObjectType($this->auditorClass)->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $operation = $this->firstPositionalString($node);
        if (null === $operation || 1 === preg_match(self::PATTERN, $operation)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Audit operation "%s" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                $operation,
            ))
                ->identifier('audit.operationNameShape')
                ->build(),
        ];
    }

    private function firstPositionalString(MethodCall $node): ?string
    {
        foreach ($node->args as $arg) {
            if (!$arg instanceof Node\Arg || $arg->name instanceof Node\Identifier) {
                continue;
            }

            return $arg->value instanceof Node\Scalar\String_ ? $arg->value->value : null;
        }

        return null;
    }
}
