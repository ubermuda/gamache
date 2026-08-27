<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * An MCP tool class and the tool name it declares must agree:
 * `DocumentReviseTool` declares `document_revise`.
 *
 * The tool name is the only handle a caller has, and the class name is the only
 * handle everyone else has — a grep, a stack trace, a bug report naming the
 * tool that failed. Let them diverge and looking up a reported tool means
 * reading every attribute in the module, which is exactly the moment nobody has
 * to spare. Nothing else notices: the server registers whatever string the
 * attribute carries, so a rename on one side ships green.
 *
 * @implements Rule<Class_>
 */
final readonly class McpToolNameRule implements Rule
{
    private const string ATTRIBUTE = 'Mcp\Capability\Attribute\McpTool';

    private const string SUFFIX = 'Tool';

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

        $attribute = $this->findAttribute($node, $scope);
        if (null === $attribute) {
            return [];
        }

        $shortName = $node->name->name;

        $declared = $this->declaredName($attribute, $node, $shortName);
        if (null === $declared) {
            return [];
        }

        $expected = self::snakeCase(self::stripSuffix($shortName));

        if ($declared === $expected) {
            return [];
        }

        $rename = self::className($declared);

        return [
            RuleErrorBuilder::message(sprintf(
                // Renaming the class is only the other half of the fix when it
                // lands somewhere else; a name that is merely not snake_case
                // maps back to the class it is already on.
                $rename === $shortName
                    ? 'MCP tool %s declares the tool name "%s"; expected "%s".'
                    : 'MCP tool %s declares the tool name "%s"; expected "%s". Rename the tool, or rename the class to %4$s.',
                $shortName,
                $declared,
                $expected,
                $rename,
            ))
                ->identifier('mcp.toolNameMismatch')
                ->line($attribute->getLine())
                ->build(),
        ];
    }

    private function findAttribute(Class_ $class, Scope $scope): ?Node\Attribute
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (self::ATTRIBUTE === $scope->resolveName($attribute->name)) {
                    return $attribute;
                }
            }
        }

        return null;
    }

    /**
     * The `name:` argument, named or first-positional. A tool that publishes its
     * own name as a constant declares it through `self::NAME`, so that is read
     * too. Null when the name is built any other way and cannot be compared.
     */
    private function declaredName(Node\Attribute $attribute, Class_ $class, string $shortName): ?string
    {
        foreach ($attribute->args as $index => $arg) {
            $isName = null === $arg->name ? 0 === $index : 'name' === $arg->name->name;

            if (!$isName) {
                continue;
            }

            if ($arg->value instanceof String_) {
                return $arg->value->value;
            }

            return $this->ownConstant($arg->value, $class, $shortName);
        }

        return null;
    }

    private function ownConstant(Node\Expr $expr, Class_ $class, string $shortName): ?string
    {
        if (!$expr instanceof Node\Expr\ClassConstFetch || !$expr->name instanceof Node\Identifier) {
            return null;
        }

        $owner = $expr->class instanceof Node\Name ? $expr->class->toString() : '';
        if ('self' !== $owner && 'static' !== $owner && $owner !== $shortName) {
            return null;
        }

        foreach ($class->getConstants() as $constant) {
            foreach ($constant->consts as $const) {
                if ($const->name->name === $expr->name->name && $const->value instanceof String_) {
                    return $const->value->value;
                }
            }
        }

        return null;
    }

    private static function stripSuffix(string $shortName): string
    {
        if (str_ends_with($shortName, self::SUFFIX) && $shortName !== self::SUFFIX) {
            return substr($shortName, 0, -\strlen(self::SUFFIX));
        }

        return $shortName;
    }

    private static function snakeCase(string $name): string
    {
        $spaced = preg_replace(
            ['/([a-z\d])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'],
            '$1_$2',
            $name,
        ) ?? $name;

        return strtolower($spaced);
    }

    private static function className(string $toolName): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace('_', ' ', $toolName)));

        return $studly.self::SUFFIX;
    }
}
