<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use Doctrine\Migrations\AbstractMigration;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A release may only expand the schema. A deployment that runs
 * `doctrine:migrations:migrate` on every release runs it on the release that
 * rolls an image back too, so the schema a rollback lands on is the newer one:
 * the previous image has to tolerate it. Adding a column, a table or a nullable
 * field is tolerable; dropping, renaming or narrowing one is not, and belongs in
 * a later release, once no deployed version still reads the old shape.
 *
 * The rule reads the SQL literals passed to `$this->addSql()` in `up()`.
 * `down()` is not analysed: it is destructive by definition and never runs in a
 * roll-forward-only deployment.
 *
 * A statement that is the contract phase of an earlier expansion says so in a
 * comment on the line above it:
 *
 *     // @contract-phase: nothing has read profiles.legacy_slug since the release before this one
 *     $this->addSql('ALTER TABLE profiles DROP legacy_slug');
 *
 * The same marker in the docblock of `up()` covers every statement in it, for a
 * migration that is nothing but a contract phase.
 *
 * `$enforcedFrom` is a `YYYYMMDDHHMMSS` timestamp at which enforcement begins:
 * a migration named `VersionYYYYMMDDHHMMSS` earlier than it is skipped, so a
 * back-catalogue that has already shipped everywhere stays as deployed. Empty
 * enforces every migration, and a class name carrying no such timestamp is
 * enforced whatever the cutoff.
 *
 * @implements Rule<ClassMethod>
 */
final readonly class MigrationExpandContractRule implements Rule
{
    private const string MARKER = '@contract-phase';

    private const string TIMESTAMP = '\d{14}';

    private const string ADVICE = 'A release may only expand the schema, so a rollback finds one the previous image tolerates; ship the contraction in a later release and mark it "// @contract-phase: <why nothing reads the old shape>" on the line above.';

    private const string IDENTIFIER = '[A-Za-z0-9_$.]+';

    /**
     * What an `ALTER TABLE ... DROP <word>` can name other than a column. None
     * of them narrows the shape the previous image reads.
     */
    private const array NOT_A_COLUMN = ['INDEX', 'KEY', 'PRIMARY', 'FOREIGN', 'UNIQUE', 'CHECK', 'PARTITION'];

    public function __construct(private string $enforcedFrom = '')
    {
        if ('' !== $enforcedFrom && 1 !== preg_match('/^'.self::TIMESTAMP.'$/', $enforcedFrom)) {
            throw new \InvalidArgumentException(sprintf(
                'gamache.migrationsEnforcedFrom must be a YYYYMMDDHHMMSS timestamp, or empty to enforce every migration; got "%s".',
                $enforcedFrom,
            ));
        }
    }

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof ClassMethod);

        if ('up' !== $node->name->name) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !$classReflection->isSubclassOf(AbstractMigration::class)) {
            return [];
        }

        if ($this->shippedBeforeEnforcement($classReflection->getName())) {
            return [];
        }

        $statements = $this->sqlStatements($node);
        if ([] === $statements) {
            return [];
        }

        $expansions = $this->expansions($statements);
        $markers = $this->markers($node);

        $errors = [];
        foreach ($markers as [$start, , $reason]) {
            if ('' !== $reason) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                'A "%s" marker must say why nothing reads the old shape any more.',
                self::MARKER,
            ))
            ->identifier('migration.contractPhaseWithoutReason')
            ->line($start)
            ->build();
        }

        foreach ($statements as [$sql, $line]) {
            if (null !== $this->markerReason($markers, $line)) {
                continue;
            }

            $contraction = $this->contraction($sql, $expansions);
            if (null === $contraction) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message($contraction.' '.self::ADVICE)
                ->identifier('migration.destructiveSql')
                ->line($line)
                ->build();
        }

        return $errors;
    }

    /**
     * A name the timestamp cannot be read from is enforced: a cutoff that
     * exempts whatever it fails to parse stops meaning anything.
     */
    private function shippedBeforeEnforcement(string $class): bool
    {
        if ('' === $this->enforcedFrom) {
            return false;
        }

        if (1 !== preg_match('/(?:^|\\\\)Version('.self::TIMESTAMP.')$/', $class, $matches)) {
            return false;
        }

        return $matches[1] < $this->enforcedFrom;
    }

    /**
     * @return list<array{string, int}> SQL literal and the line it is written on
     */
    private function sqlStatements(ClassMethod $node): array
    {
        $statements = [];

        /** @var MethodCall[] $calls */
        $calls = (new NodeFinder())->findInstanceOf($node, MethodCall::class);

        foreach ($calls as $call) {
            if (!$call->name instanceof Node\Identifier || 'addSql' !== $call->name->name) {
                continue;
            }

            $argument = $call->getArgs()[0] ?? null;
            if (null === $argument || !$argument->value instanceof Node\Scalar\String_) {
                continue;
            }

            $statements[] = [$this->normalise($argument->value->value), $call->getLine()];
        }

        return $statements;
    }

    /**
     * The tables, constraints and defaults this migration itself puts in place.
     * A migration that creates a table owns everything it then does to it, a
     * constraint dropped and re-added under the same name is rebuilt rather than
     * removed, and a column with a non-null default stays writable by code that
     * does not know it exists.
     *
     * @param list<array{string, int}> $statements
     *
     * @return array{tables: list<string>, constraints: list<string>, defaults: list<string>}
     */
    private function expansions(array $statements): array
    {
        $tables = [];
        $constraints = [];
        $defaults = [];

        foreach ($statements as [$sql]) {
            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?"?('.self::IDENTIFIER.')"?/i', $sql, $matches)) {
                $tables[] = strtolower($matches[1]);

                continue;
            }

            if (!preg_match('/^ALTER TABLE (?:ONLY )?"?('.self::IDENTIFIER.')"?\s+(.*)$/i', $sql, $matches)) {
                continue;
            }

            $table = strtolower($matches[1]);
            $rest = $matches[2];

            if (preg_match('/^ADD CONSTRAINT "?('.self::IDENTIFIER.')"?/i', $rest, $constraint)) {
                $constraints[] = strtolower($constraint[1]);

                continue;
            }

            if (preg_match('/^ALTER (?:COLUMN )?"?('.self::IDENTIFIER.')"? SET DEFAULT (?!NULL\b)/i', $rest, $column)) {
                $defaults[] = $table.'.'.strtolower($column[1]);

                continue;
            }

            if (preg_match('/^ADD (?:COLUMN )?"?('.self::IDENTIFIER.')"? .*\bDEFAULT (?!NULL\b)/i', $rest, $column)) {
                $defaults[] = $table.'.'.strtolower($column[1]);
            }
        }

        return ['tables' => $tables, 'constraints' => $constraints, 'defaults' => $defaults];
    }

    /**
     * @param array{tables: list<string>, constraints: list<string>, defaults: list<string>} $expansions
     *
     * @return string|null the contraction this statement performs, or null if it only expands
     */
    private function contraction(string $sql, array $expansions): ?string
    {
        if (preg_match('/^DROP TABLE (?:IF EXISTS )?"?('.self::IDENTIFIER.')"?/i', $sql, $matches)) {
            return \in_array(strtolower($matches[1]), $expansions['tables'], true)
                ? null
                : sprintf('Migration up() drops table "%s".', $matches[1]);
        }

        if (!preg_match('/^ALTER TABLE (?:ONLY )?"?('.self::IDENTIFIER.')"?\s+(.*)$/i', $sql, $matches)) {
            return null;
        }

        $table = $matches[1];
        $rest = $matches[2];

        if (\in_array(strtolower($table), $expansions['tables'], true)) {
            return null;
        }

        if (preg_match('/^DROP CONSTRAINT (?:IF EXISTS )?"?('.self::IDENTIFIER.')"?/i', $rest, $constraint)) {
            return \in_array(strtolower($constraint[1]), $expansions['constraints'], true)
                ? null
                : sprintf('Migration up() drops constraint "%s" from table "%s".', $constraint[1], $table);
        }

        if (preg_match('/^DROP (?:COLUMN )?(?:IF EXISTS )?"?('.self::IDENTIFIER.')"?/i', $rest, $column)) {
            return \in_array(strtoupper($column[1]), self::NOT_A_COLUMN, true)
                ? null
                : sprintf('Migration up() drops column "%s" from table "%s".', $column[1], $table);
        }

        if (preg_match('/^RENAME TO "?('.self::IDENTIFIER.')"?/i', $rest, $renamed)) {
            return sprintf('Migration up() renames table "%s" to "%s".', $table, $renamed[1]);
        }

        if (preg_match('/^RENAME (?:COLUMN )?"?('.self::IDENTIFIER.')"? TO "?('.self::IDENTIFIER.')"?/i', $rest, $renamed)) {
            return sprintf('Migration up() renames column "%s" on table "%s" to "%s".', $renamed[1], $table, $renamed[2]);
        }

        if (!preg_match('/^ALTER (?:COLUMN )?"?('.self::IDENTIFIER.')"?\s+(.*)$/i', $rest, $altered)) {
            return null;
        }

        $column = $altered[1];
        $change = $altered[2];

        if (preg_match('/^TYPE\b/i', $change)) {
            return sprintf('Migration up() changes the type of column "%s" on table "%s".', $column, $table);
        }

        if (preg_match('/^SET NOT NULL\b/i', $change)) {
            return \in_array($table.'.'.strtolower($column), $expansions['defaults'], true)
                ? null
                : sprintf('Migration up() makes column "%s" on table "%s" NOT NULL with no default to fill it.', $column, $table);
        }

        return null;
    }

    /**
     * Every marker comment, with the line range of the statement it exempts. A
     * comment only ever attaches to the statement below it, so a marker sits on
     * its own line above the `addSql()` call it covers.
     *
     * @return list<array{int, int, string}> first line, last line, and the reason given
     */
    private function markers(ClassMethod $node): array
    {
        $markers = [];

        /** @var Node\Stmt[] $statements */
        $statements = (new NodeFinder())->findInstanceOf($node, Node\Stmt::class);

        foreach ($statements as $statement) {
            foreach ($statement->getComments() as $comment) {
                $reason = $this->reasonIn($comment);
                if (null === $reason || $this->trailsAnotherStatement($comment, $statement, $statements)) {
                    continue;
                }

                $markers[] = [$statement->getStartLine(), $statement->getEndLine(), $reason];
            }
        }

        return $markers;
    }

    /**
     * A comment written after a statement on the same line is attached to the
     * *next* one, which would exempt a statement nobody meant to exempt. Such a
     * comment is not a marker, so the statement it trails stays reported and its
     * author sees where the marker belongs.
     *
     * @param Node\Stmt[] $statements
     */
    private function trailsAnotherStatement(Comment $comment, Node\Stmt $owner, array $statements): bool
    {
        foreach ($statements as $statement) {
            if ($statement !== $owner && !$statement instanceof Node\Stmt\Nop && $statement->getEndLine() === $comment->getStartLine()) {
                return true;
            }
        }

        return false;
    }

    private function reasonIn(Comment $comment): ?string
    {
        if (!preg_match('/'.preg_quote(self::MARKER, '/').'\b\s*:?(.*)$/mi', $comment->getText(), $matches)) {
            return null;
        }

        return trim($matches[1], " \t*/");
    }

    /**
     * @param list<array{int, int, string}> $markers
     *
     * @return string|null the reason given for the statement on this line, or null if it carries no marker
     */
    private function markerReason(array $markers, int $line): ?string
    {
        foreach ($markers as [$start, $end, $reason]) {
            if ($line >= $start && $line <= $end) {
                return $reason;
            }
        }

        return null;
    }

    private function normalise(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
