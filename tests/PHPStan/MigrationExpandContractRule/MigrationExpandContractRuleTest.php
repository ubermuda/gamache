<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\MigrationExpandContractRule;

use Gamache\PHPStan\MigrationExpandContractRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MigrationExpandContractRule>
 */
final class MigrationExpandContractRuleTest extends RuleTestCase
{
    private const string ADVICE = 'A release may only expand the schema, so a rollback finds one the previous image tolerates; ship the contraction in a later release and mark it "// @contract-phase: <why nothing reads the old shape>" on the line above.';

    private string $enforcedFrom = '';

    protected function getRule(): Rule
    {
        return new MigrationExpandContractRule($this->enforcedFrom);
    }

    public function test_an_expanding_migration_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_every_contracting_statement_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            ['Migration up() drops table "waitlist_entries". '.self::ADVICE, 18],
            ['Migration up() drops column "project_id" from table "documents". '.self::ADVICE, 19],
            ['Migration up() drops constraint "FK_A2B07288166D1F9C" from table "documents". '.self::ADVICE, 20],
            ['Migration up() renames column "site_id" on table "site_review_reviews" to "project_id". '.self::ADVICE, 21],
            ['Migration up() renames table "site_review_sites" to "projects". '.self::ADVICE, 22],
            ['Migration up() changes the type of column "full_name" on table "users". '.self::ADVICE, 23],
            ['Migration up() makes column "username" on table "users" NOT NULL with no default to fill it. '.self::ADVICE, 24],
            ['Migration up() drops table "document_highlights". '.self::ADVICE, 25],
            ['Migration up() drops table "document_tags". '.self::ADVICE, 29],
            ['Migration up() drops column "nickname" from table "users". '.self::ADVICE, 30],
        ]);
    }

    public function test_a_new_column_made_not_null_by_a_backfill_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/backfilled_not_null.php'], [
            ['Migration up() makes column "sequence" on table "reviews" NOT NULL with no default to fill it. '.self::ADVICE, 20],
        ]);
    }

    public function test_shapes_this_migration_puts_in_place_itself_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/exempt.php'], []);
    }

    public function test_a_marked_contraction_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/marked.php'], []);
    }

    public function test_a_marker_without_a_reason_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/marker_without_reason.php'], [
            ['A "@contract-phase" marker must say why nothing reads the old shape any more.', 19],
            ['Migration up() drops column "legacy_avatar" from table "profiles". '.self::ADVICE, 21],
            ['Migration up() drops column "legacy_bio" from table "profiles". '.self::ADVICE, 22],
        ]);
    }

    public function test_a_table_recreated_after_being_dropped_is_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/recreated_table.php'], [
            ['Migration up() drops table "projects". '.self::ADVICE, 18],
        ]);
    }

    public function test_no_cutoff_reports_every_migration(): void
    {
        $this->analyse([__DIR__.'/Fixture/timestamped.php'], [
            ['Migration up() drops column "legacy_slug" from table "profiles". '.self::ADVICE, 18],
        ]);
    }

    public function test_a_migration_older_than_the_cutoff_is_skipped(): void
    {
        $this->enforcedFrom = '20260102000000';

        $this->analyse([__DIR__.'/Fixture/timestamped.php'], []);
    }

    public function test_a_migration_at_the_cutoff_is_reported(): void
    {
        $this->enforcedFrom = '20260101000000';

        $this->analyse([__DIR__.'/Fixture/timestamped.php'], [
            ['Migration up() drops column "legacy_slug" from table "profiles". '.self::ADVICE, 18],
        ]);
    }

    public function test_a_migration_after_the_cutoff_is_reported(): void
    {
        $this->enforcedFrom = '20251231235959';

        $this->analyse([__DIR__.'/Fixture/timestamped.php'], [
            ['Migration up() drops column "legacy_slug" from table "profiles". '.self::ADVICE, 18],
        ]);
    }

    public function test_a_class_name_carrying_no_timestamp_is_reported_whatever_the_cutoff(): void
    {
        $this->enforcedFrom = '20991231235959';

        $this->analyse([__DIR__.'/Fixture/backfilled_not_null.php'], [
            ['Migration up() makes column "sequence" on table "reviews" NOT NULL with no default to fill it. '.self::ADVICE, 20],
        ]);
    }

    public function test_a_cutoff_that_is_not_a_timestamp_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('gamache.migrationsEnforcedFrom must be a YYYYMMDDHHMMSS timestamp');

        new MigrationExpandContractRule('2026-08-27');
    }
}
