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

/**
 * A controller that injects anything must inject a handler, so that the work
 * it does has a name and a home outside the request.
 *
 * A controller is a request/response shell. Work written into it is reachable
 * only through HTTP, so the second front door onto the same domain — a
 * console command, an MCP tool, a test — either cannot have it or writes its
 * own copy. The handler is also the layer a unit test can call without a
 * kernel.
 *
 * The rule asks for a constructor parameter whose type name ends in `Handler`,
 * which is the shape a Command/Handler pair takes. It cannot ask whether the
 * handler is the one that does the work, so a controller that injects a
 * handler and still reaches for a repository passes here and is reported by
 * ControllerNoDirectStateAccessRule instead. The two rules answer different
 * halves of the same convention.
 *
 * A controller that declares no constructor is exempt. It injects nothing, so
 * it renders a template and returns, and there is no work for a handler to
 * carry. The exemption is the constructor and nothing else: a GET route, or an
 * action whose body is one `render()` call, says nothing about what the
 * controller was given. An empty `__construct() {}` is reported, which is
 * correct and cheap to fix — delete it.
 *
 * The rule reads the constructor the controller declares itself. Every
 * controller in a project with a base controller is a leaf class, and the base
 * takes no handler on anyone's behalf, so there is nothing above to follow.
 * McpToolHandlerRule does follow a parent, because a base tool class that
 * holds the handler is a shape tools take.
 *
 * `ignoredControllers` exempts named classes. A route whose work the framework
 * does — a login form the security firewall posts to — has collaborators and
 * no handler to wrap, and no AST signal separates it from a controller that
 * should delegate. The list is the consuming project's to fill, and is empty
 * by default.
 *
 * @implements Rule<Class_>
 */
final readonly class ControllerHandlerRule implements Rule
{
    private InjectedHandlerFinder $finder;

    /**
     * @param list<string> $ignoredControllers
     */
    public function __construct(
        private ReflectionProvider $reflectionProvider,
        private string $controllerBaseClass,
        private array $ignoredControllers = [],
    ) {
        $this->finder = new InjectedHandlerFinder();
    }

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

        if (!$this->isController($node, $scope)) {
            return [];
        }

        if (\in_array($this->fqcn($node), $this->ignoredControllers, true)) {
            return [];
        }

        if (null === InjectedHandlerFinder::constructor($node)) {
            return [];
        }

        if ($this->finder->injects($node, $scope)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Controller %s injects no handler; give it a Command/Handler pair to delegate to.',
                $node->name->name,
            ))
                ->identifier('controller.missingHandler')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isController(Class_ $node, Scope $scope): bool
    {
        $fqcn = $this->fqcn($node);

        if ($this->reflectionProvider->hasClass($fqcn)) {
            return $this->reflectionProvider->getClass($fqcn)->isSubclassOf($this->controllerBaseClass);
        }

        // Reflection not available (e.g. global-namespace fixture): fall back to AST parent check.
        return null !== $node->extends
            && $this->controllerBaseClass === $scope->resolveName($node->extends);
    }

    private function fqcn(Class_ $node): string
    {
        return null !== $node->namespacedName
            ? $node->namespacedName->toString()
            : $node->name->name ?? '';
    }
}
