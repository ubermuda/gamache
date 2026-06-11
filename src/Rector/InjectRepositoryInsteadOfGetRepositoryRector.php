<?php

declare(strict_types=1);

namespace Gamache\Rector;

use Doctrine\ORM\Mapping\Entity as OrmEntity;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces $em->getRepository(Entity::class) call sites with an injected
 * promoted constructor property typed to the repository declared on the entity's
 * #[ORM\Entity(repositoryClass:)] attribute.
 *
 * Skips entities with no custom repositoryClass (base EntityRepository provides
 * no value as a dedicated injection target).
 */
final class InjectRepositoryInsteadOfGetRepositoryRector extends AbstractRector
{
    public function __construct(
        private readonly BetterNodeFinder $betterNodeFinder,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace $em->getRepository(Entity::class) with an injected promoted repository property',
            [
                new CodeSample(
                    <<<'CODE'
class SomeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function doSomething(): void
    {
        $user = $this->em->getRepository(User::class)->find(1);
    }
}
CODE,
                    <<<'CODE'
class SomeService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {}

    public function doSomething(): void
    {
        $user = $this->userRepository->find(1);
    }
}
CODE
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /** @param Class_ $node */
    public function refactor(Node $node): ?Node
    {
        /** @var array<int, array{propertyName: string, repositoryFqcn: string}> spl_object_id => info */
        $callMap = [];

        // Pass 1: collect every getRepository() call on an EntityManagerInterface,
        // resolve the repository FQCN from the entity's ORM attribute.
        foreach ($this->betterNodeFinder->findInstanceOf($node->stmts ?? [], MethodCall::class) as $call) {
            if (!$this->isName($call->name, 'getRepository')) {
                continue;
            }

            $callerType = $this->getType($call->var);
            if (!new ObjectType(\Doctrine\ORM\EntityManagerInterface::class)->isSuperTypeOf($callerType)->yes()) {
                continue;
            }

            $args = $call->getArgs();
            if (1 !== count($args)) {
                continue;
            }

            $argValue = $args[0]->value;
            if (!$argValue instanceof ClassConstFetch || !$this->isName($argValue->name, 'class')) {
                continue;
            }

            $entityFqcn = $this->getName($argValue->class);
            if (null === $entityFqcn) {
                continue;
            }

            $repositoryFqcn = $this->resolveRepositoryFqcn($entityFqcn);
            if (null === $repositoryFqcn || !class_exists($repositoryFqcn)) {
                continue;
            }

            $propertyName = lcfirst(new \ReflectionClass($repositoryFqcn)->getShortName());

            $callMap[spl_object_id($call)] = [
                'propertyName' => $propertyName,
                'repositoryFqcn' => $repositoryFqcn,
            ];
        }

        if ([] === $callMap) {
            return null;
        }

        // Pass 2: replace each matched MethodCall with $this->xRepository.
        // traverseNodesWithCallable visits the same objects found above, so
        // spl_object_id identity is stable across both passes.
        $this->traverseNodesWithCallable($node->stmts ?? [], static function (Node $innerNode) use ($callMap): ?PropertyFetch {
            if (!$innerNode instanceof MethodCall) {
                return null;
            }
            $info = $callMap[spl_object_id($innerNode)] ?? null;
            if (null === $info) {
                return null;
            }

            return new PropertyFetch(new Variable('this'), new Identifier($info['propertyName']));
        });

        // Deduplicate: same repository referenced multiple times → inject once.
        $toInject = [];
        foreach ($callMap as ['propertyName' => $prop, 'repositoryFqcn' => $fqcn]) {
            $toInject[$prop] = $fqcn;
        }

        $this->addPromotedProperties($node, $toInject);

        return $node;
    }

    /**
     * Reads the entity's #[ORM\Entity(repositoryClass:)] attribute at runtime.
     * Returns null when there is no custom repository (base EntityRepository).
     *
     * @return class-string|null
     */
    private function resolveRepositoryFqcn(string $entityFqcn): ?string
    {
        if (!class_exists($entityFqcn)) {
            return null;
        }

        $reflClass = new \ReflectionClass($entityFqcn);

        $attrs = $reflClass->getAttributes(OrmEntity::class);
        if ([] === $attrs) {
            return null;
        }

        $repositoryClass = $attrs[0]->getArguments()['repositoryClass'] ?? null;

        if (!is_string($repositoryClass) || '' === $repositoryClass || !class_exists($repositoryClass)) {
            return null;
        }

        return $repositoryClass;
    }

    /**
     * Adds a `private readonly XRepository $xRepository` promoted property to
     * the constructor, creating the constructor if the class has none.
     * Already-injected repositories (matched by property name) are skipped.
     *
     * @param array<string, string> $toInject propertyName => FQCN
     */
    private function addPromotedProperties(Class_ $class, array $toInject): void
    {
        $constructor = array_find($class->getMethods(), fn ($method) => $this->isName($method->name, '__construct'));
        if (null === $constructor) {
            $constructor = new ClassMethod('__construct', ['stmts' => []]);
            array_unshift($class->stmts, $constructor);
        }

        foreach ($toInject as $propertyName => $repositoryFqcn) {
            foreach ($constructor->params as $existing) {
                if ($this->isName($existing->var, $propertyName)) {
                    continue 2; // already present
                }
            }

            $param = new Param(
                new Variable($propertyName),
                default: null,
                type: new FullyQualified($repositoryFqcn),
            );
            $param->flags = Class_::MODIFIER_PRIVATE | Class_::MODIFIER_READONLY;

            $constructor->params[] = $param;
        }
    }
}
