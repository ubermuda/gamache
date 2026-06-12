<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Constructor parameters typed with a `*Repository` class must be named after
 * the pluralized entity noun: `WorkspaceRepository $workspaces`, not
 * `WorkspaceRepository $workspaceRepo`. The expected name is derived from the
 * repository class name (suffix stripped, lcfirst, pluralized).
 *
 * @implements Rule<ClassMethod>
 */
final readonly class RepositoryParameterNameRule implements Rule
{
    private const string SUFFIX = 'Repository';

    private Inflector $inflector;

    /** @param list<string> $excludedClasses repository FQCNs exempt from the convention (e.g. Doctrine base repositories) */
    public function __construct(
        private array $excludedClasses = [],
    ) {
        $this->inflector = InflectorFactory::create()->build();
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof ClassMethod);

        if ('__construct' !== $node->name->toLowerString()) {
            return [];
        }

        $errors = [];
        foreach ($node->params as $param) {
            $type = $param->type;
            if ($type instanceof Node\NullableType) {
                $type = $type->type;
            }
            if (!$type instanceof Name) {
                continue;
            }

            $fqcn = $scope->resolveName($type);
            if (\in_array($fqcn, $this->excludedClasses, true)) {
                continue;
            }

            $shortName = false === ($pos = strrpos($fqcn, '\\')) ? $fqcn : substr($fqcn, $pos + 1);
            if (!str_ends_with($shortName, self::SUFFIX)) {
                continue;
            }

            $entity = substr($shortName, 0, -\strlen(self::SUFFIX));
            if ('' === $entity) {
                // A class named exactly "Repository" (e.g. an entity) is not a repository type.
                continue;
            }

            if (!$param->var instanceof Variable || !\is_string($param->var->name)) {
                continue;
            }

            $expected = $this->inflector->pluralize(lcfirst($entity));
            if ($param->var->name === $expected) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Constructor parameter $%s typed %s must be named $%s (pluralized entity noun).',
                $param->var->name,
                $shortName,
                $expected,
            ))
            ->identifier('repository.parameterName')
            ->line($param->getLine())
            ->build();
        }

        return $errors;
    }
}
