<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\AuditOperationNameRule;

use Gamache\PHPStan\AuditOperationNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AuditOperationNameRule>
 */
final class AuditOperationNameRuleTest extends RuleTestCase
{
    private string $auditorClass = 'App\Module\Audit\Auditor';

    protected function getRule(): Rule
    {
        return new AuditOperationNameRule($this->auditorClass, ['record']);
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/config.neon'];
    }

    public function test_two_snake_case_segments_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_malformed_operation_names_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Audit operation "billing.webhook.received" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                11,
            ],
            [
                'Audit operation "billing_comp_granted" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                16,
            ],
            [
                'Audit operation "Billing.compGranted" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                21,
            ],
            [
                'Audit operation "2fa.enabled" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                26,
            ],
            [
                'Audit operation "billing._comp_granted" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                31,
            ],
            [
                'Audit operation "billing.comp.granted" must be <module>.<outcome>: exactly two snake_case segments separated by one dot.',
                43,
            ],
        ]);
    }

    public function test_an_unconfigured_auditor_class_turns_the_rule_off(): void
    {
        $this->auditorClass = '';

        $this->analyse([__DIR__.'/Fixture/violation.php'], []);
    }
}
