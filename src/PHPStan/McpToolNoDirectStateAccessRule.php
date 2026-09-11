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
 * An MCP tool must never read or mutate persistent state directly: every read
 * and every write goes through a Command/Handler, exactly as a controller's does.
 *
 * A tool is the agent-facing front door onto the same domain the web front door
 * serves. Query a repository from the tool and that query is outside every rule
 * the handler enforces, and outside every test written against it. The two
 * doors then answer differently, and the difference shows up as an agent that
 * can do something the UI forbids.
 *
 * The rule flags any call inside a tool on an injected Doctrine persistence
 * collaborator — the EntityManager/ObjectManager, the DBAL Connection, or a
 * repository. Handlers are invoked as a callable (`($this->handler)(...)`),
 * which is a FuncCall rather than a MethodCall, so delegation is exempt, and so
 * is a call on any collaborator that is not a persistence type.
 *
 * Injecting a handler is not a defence: a tool that delegates its write and
 * still reads a repository for the response is the common shape, and is exactly
 * what this rule is for. Whether a handler is injected at all is
 * McpToolHandlerRule's question.
 *
 * @implements Rule<Class_>
 */
final readonly class McpToolNoDirectStateAccessRule implements Rule
{
    private const string ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private DirectStateAccessFinder $finder;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->finder = new DirectStateAccessFinder($reflectionProvider);
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

        if (!$this->isTool($node, $scope)) {
            return [];
        }

        return array_map(
            fn (array $finding): RuleError => RuleErrorBuilder::message(sprintf(
                'MCP tool %s must not access persistent state directly (%s()); read and write through a Command/Handler.',
                $node->name->name,
                $finding[0],
            ))
                ->identifier('mcp.directStateAccess')
                ->line($finding[1])
                ->build(),
            $this->finder->findings($node, $scope),
        );
    }

    private function isTool(Class_ $class, Scope $scope): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (self::ATTRIBUTE === $scope->resolveName($attribute->name)) {
                    return true;
                }
            }
        }

        return false;
    }
}
