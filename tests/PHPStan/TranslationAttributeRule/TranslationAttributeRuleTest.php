<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\TranslationAttributeRule;

use Gamache\PHPStan\TranslationAttributeRule;
use Gamache\PHPStan\TranslationKeyValidator;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Symfony\Component\Validator\Constraint;

/**
 * @extends RuleTestCase<TranslationAttributeRule>
 */
final class TranslationAttributeRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TranslationAttributeRule(
            new TranslationKeyValidator(),
            $this->createReflectionProvider(),
            [
                [
                    'class' => Constraint::class,
                    'argumentNames' => ['message', 'minMessage', 'maxMessage', 'exactMessage'],
                ],
            ],
        );
    }

    public function test_valid_keys_produce_no_errors(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_positional_natural_language_in_attribute_args_is_flagged(): void
    {
        $this->analyse([__DIR__.'/Fixture/positional_violation.php'], [
            [
                'Attribute argument "message" of #[Symfony\Component\Validator\Constraints\NotBlank] must be a translation key (e.g. "account.registration.validator.email_unique"), got "Please fill this in.".',
                10,
            ],
        ]);
    }

    public function test_natural_language_in_attribute_args_is_flagged(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Attribute argument "message" of #[Symfony\Component\Validator\Constraints\NotBlank] must be a translation key (e.g. "account.registration.validator.email_unique"), got "This field should not be blank.".',
                9,
            ],
            [
                'Attribute argument "minMessage" of #[Symfony\Component\Validator\Constraints\Length] must be a translation key (e.g. "account.registration.validator.email_unique"), got "Too short".',
                12,
            ],
            [
                'Attribute argument "maxMessage" of #[Symfony\Component\Validator\Constraints\Length] must be a translation key (e.g. "account.registration.validator.email_unique"), got "Too long".',
                12,
            ],
        ]);
    }
}
