<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A JSON API controller (in a "\Controller\Api\" namespace) binds input through
 * #[MapRequestPayload] and a validated DTO — never a Symfony form nor a
 * hand-rolled read of the raw request body. This flags the forbidden
 * constructs; it does not require a payload parameter (a read endpoint has none).
 *
 * @implements Rule<Class_>
 */
final readonly class ApiControllerInputBindingRule implements Rule
{
    /** @var list<string> */
    private const array FORM_TYPES = [
        'Symfony\\Component\\Form\\FormInterface',
        'Symfony\\Component\\Form\\FormFactoryInterface',
    ];

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

        if (!str_contains($fqcn, '\\Controller\\Api\\')) {
            return [];
        }

        $class = $node->name->name;
        $finder = new NodeFinder();
        $errors = [];

        // $this->createForm(...) — building a Symfony form.
        foreach ($finder->find($node, static fn (Node $n): bool => self::isThisCall($n, 'createForm')) as $call) {
            $errors[] = $this->error(
                sprintf('API controller %s must bind input via #[MapRequestPayload], not a Symfony form ($this->createForm()).', $class),
                $call->getStartLine(),
            );
        }

        // ->getContent() — reading the raw request body to hand-parse it.
        foreach ($finder->find($node, static fn (Node $n): bool => $n instanceof MethodCall && $n->name instanceof Node\Identifier && 'getContent' === $n->name->name) as $call) {
            $errors[] = $this->error(
                sprintf('API controller %s must bind input via #[MapRequestPayload], not raw request body parsing (->getContent()).', $class),
                $call->getStartLine(),
            );
        }

        // Any reference to the Form component (injected factory, form type hints).
        foreach ($finder->findInstanceOf($node, Node\Name::class) as $name) {
            if (\in_array($scope->resolveName($name), self::FORM_TYPES, true)) {
                $errors[] = $this->error(
                    sprintf('API controller %s must not depend on the Symfony Form component; bind input via #[MapRequestPayload].', $class),
                    $name->getStartLine(),
                );
            }
        }

        return $errors;
    }

    private static function isThisCall(Node $node, string $method): bool
    {
        return $node instanceof MethodCall
            && $node->var instanceof Variable
            && 'this' === $node->var->name
            && $node->name instanceof Node\Identifier
            && $method === $node->name->name;
    }

    private function error(string $message, int $line): RuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier('controller.apiInputBinding')
            ->line($line)
            ->build();
    }
}
