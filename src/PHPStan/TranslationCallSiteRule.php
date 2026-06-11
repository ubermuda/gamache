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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements Rule<MethodCall>
 *
 * @phpstan-type CallSite array{class: string, method: string, argumentIndex: int, argumentName?: string}
 */
final readonly class TranslationCallSiteRule implements Rule
{
    /**
     * @param list<CallSite> $callSites
     */
    public function __construct(
        private TranslationKeyValidator $validator,
        private array $callSites,
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

        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->name;
        $callerType = $scope->getType($node->var);
        $errors = [];

        // Run the built-in TranslatorInterface::trans() check only when no callSite entry already
        // covers it — otherwise the same violation would be reported twice.
        if (
            'trans' === $methodName
            && new ObjectType(TranslatorInterface::class)->isSuperTypeOf($callerType)->yes()
        ) {
            $handledByCallSites = array_any($this->callSites, fn ($site) => 'trans' === $site['method'] && new ObjectType($site['class'])->isSuperTypeOf($callerType)->yes());
            if (!$handledByCallSites) {
                $errors = [...$errors, ...$this->checkArgument($node, 0, 'TranslatorInterface::trans()', 'id')];
            }
        }

        foreach ($this->callSites as $site) {
            if ($methodName !== $site['method']) {
                continue;
            }
            if (!new ObjectType($site['class'])->isSuperTypeOf($callerType)->yes()) {
                continue;
            }
            $label = $site['class'].'::'.$site['method'].'()';
            $errors = [...$errors, ...$this->checkArgument($node, $site['argumentIndex'], $label, $site['argumentName'] ?? null)];
        }

        return $errors;
    }

    /** @return list<RuleError> */
    private function checkArgument(MethodCall $node, int $index, string $context, ?string $paramName = null): array
    {
        $arg = null;

        // If a parameter name is provided, look for a named argument first.
        // This handles calls like $translator->trans(parameters: [], id: 'Welcome back').
        if (null !== $paramName) {
            foreach ($node->args as $candidate) {
                if (
                    $candidate instanceof Node\Arg
                    && $candidate->name instanceof Node\Identifier
                    && $candidate->name->name === $paramName
                ) {
                    $arg = $candidate;
                    break;
                }
            }
        }

        // Fall back to resolving by positional index, counting only unnamed (positional) args.
        if (null === $arg) {
            $positionalCount = 0;
            foreach ($node->args as $candidate) {
                if ($candidate instanceof Node\Arg && !$candidate->name instanceof Node\Identifier) {
                    if ($positionalCount === $index) {
                        $arg = $candidate;
                        break;
                    }
                    ++$positionalCount;
                }
            }
        }

        if (null === $arg || !$arg->value instanceof Node\Scalar\String_) {
            return [];
        }

        $value = $arg->value->value;
        if ($this->validator->isValid($value)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Argument %d of %s must be a translation key (e.g. "account.login.heading"), got "%s".',
                $index + 1,
                $context,
                $value,
            ))
            ->identifier('translation.keyRequired')
            ->build(),
        ];
    }
}
