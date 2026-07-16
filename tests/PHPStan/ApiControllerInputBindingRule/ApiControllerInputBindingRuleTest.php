<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ApiControllerInputBindingRule;

use Gamache\PHPStan\ApiControllerInputBindingRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ApiControllerInputBindingRule>
 */
final class ApiControllerInputBindingRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ApiControllerInputBindingRule();
    }

    public function test_payload_bound_and_non_api_controllers_pass(): void
    {
        $this->analyse([
            __DIR__.'/Fixture/valid_api.php',
            __DIR__.'/Fixture/valid_web.php',
        ], []);
    }

    public function test_forbidden_constructs_are_reported(): void
    {
        $form = 'API controller BadApiController must bind input via #[MapRequestPayload], not a Symfony form ($this->createForm()).';
        $raw = 'API controller BadApiController must bind input via #[MapRequestPayload], not raw request body parsing (->getContent()).';
        $dep = 'API controller BadApiController must not depend on the Symfony Form component; bind input via #[MapRequestPayload].';

        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [$dep, 13],
            [$form, 19],
            [$raw, 20],
        ]);
    }
}
