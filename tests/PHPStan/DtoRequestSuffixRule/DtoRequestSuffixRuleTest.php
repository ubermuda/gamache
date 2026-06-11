<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\DtoRequestSuffixRule;

use Gamache\PHPStan\DtoRequestSuffixRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DtoRequestSuffixRule>
 */
final class DtoRequestSuffixRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DtoRequestSuffixRule($this->createReflectionProvider());
    }

    /** @return list<string> */
    #[\Override]
    public static function getAdditionalConfigFiles(): array
    {
        $neon = sys_get_temp_dir().'/phpstan-dto-request-suffix-fixture.neon';
        file_put_contents($neon, sprintf(
            "parameters:\n    bootstrapFiles:\n        - %s\n        - %s\n",
            __DIR__.'/Fixture/valid.php',
            __DIR__.'/Fixture/violation.php',
        ));

        return array_values([...parent::getAdditionalConfigFiles(), $neon]);
    }

    public function test_correctly_named_classes_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_dto_without_request_suffix_is_reported(): void
    {
        $msg = 'DTO class %s in a Form/ namespace must be named with a "Request" suffix (e.g. %sRequest).';

        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [sprintf($msg, 'CreateProject', 'CreateProject'), 8],
            [sprintf($msg, 'UpdateProject', 'UpdateProject'), 12],
        ]);
    }
}
