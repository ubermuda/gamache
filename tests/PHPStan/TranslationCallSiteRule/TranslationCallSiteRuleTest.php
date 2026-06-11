<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\TranslationCallSiteRule;

use Gamache\PHPStan\TranslationCallSiteRule;
use Gamache\PHPStan\TranslationKeyValidator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<TranslationCallSiteRule>
 */
final class TranslationCallSiteRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TranslationCallSiteRule(
            new TranslationKeyValidator(),
            [
                ['class' => 'App\Controller\AppController', 'method' => 'addFlash', 'argumentIndex' => 1],
                ['class' => 'Symfony\Component\Mime\Email', 'method' => 'subject', 'argumentIndex' => 0],
            ],
        );
    }

    public function test_valid_keys_produce_no_errors(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_natural_language_strings_are_flagged(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Argument 1 of TranslatorInterface::trans() must be a translation key (e.g. "account.login.heading"), got "Welcome back".',
                9,
            ],
            [
                'Argument 1 of TranslatorInterface::trans() must be a translation key (e.g. "account.login.heading"), got "Sign in to continue to your account.".',
                10,
            ],
        ]);
    }

    public function test_configured_call_site_is_flagged(): void
    {
        $this->analyse([__DIR__.'/Fixture/call_site_violation.php'], [
            [
                'Argument 1 of Symfony\Component\Mime\Email::subject() must be a translation key (e.g. "account.login.heading"), got "Password reset confirmation".',
                9,
            ],
        ]);
    }

    public function test_named_argument_violations_are_flagged(): void
    {
        $this->analyse([__DIR__.'/Fixture/named_arg_violation.php'], [
            [
                'Argument 1 of TranslatorInterface::trans() must be a translation key (e.g. "account.login.heading"), got "Welcome back".',
                9,
            ],
            [
                'Argument 1 of TranslatorInterface::trans() must be a translation key (e.g. "account.login.heading"), got "Sign in to continue.".',
                10,
            ],
        ]);
    }

    public function test_variable_argument_passes(): void
    {
        $this->analyse([__DIR__.'/Fixture/variable_arg.php'], []);
    }
}
