<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Ternary;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags a self-assigning ternary — `$x = $cond ? $x : $new` (or
 * `$x = $cond ? $new : $x`), where one branch assigns the target back to itself.
 * The self-assign is dead motion; rewrite as an `if` that mutates only on the
 * branch that changes state: `if (!$cond) { $x = $new; }`.
 *
 * The elvis short-ternary (`$x = $x ?: $default`) is intentionally NOT flagged —
 * it is the distinct, legitimate "keep-or-default" idiom.
 *
 * @implements Rule<Assign>
 */
final readonly class SelfAssigningTernaryRule implements Rule
{
    private Standard $printer;

    public function __construct()
    {
        $this->printer = new Standard();
    }

    public function getNodeType(): string
    {
        return Assign::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Assign);

        $ternary = $node->expr;
        // A full ternary only — the elvis form (`if` is null) is the legitimate
        // keep-or-default idiom and must not be flagged.
        if (!$ternary instanceof Ternary || null === $ternary->if) {
            return [];
        }

        $target = $this->printer->prettyPrintExpr($node->var);
        $selfAssigns = $this->printer->prettyPrintExpr($ternary->if) === $target
            || $this->printer->prettyPrintExpr($ternary->else) === $target;
        if (!$selfAssigns) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Self-assigning ternary: one branch assigns the target back to itself; rewrite as an `if` that assigns only on the branch that changes state.',
            )
                ->identifier('assignment.selfAssigningTernary')
                ->line($node->getLine())
                ->build(),
        ];
    }
}
