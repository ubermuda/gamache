<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ModuleTemplateNamespaceRule;

use Gamache\PHPStan\ModuleTemplateNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * An empty forbiddenPathPrefix (the default) turns the rule off entirely.
 *
 * @extends RuleTestCase<ModuleTemplateNamespaceRule>
 */
final class DisabledModuleTemplateNamespaceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ModuleTemplateNamespaceRule('', ['render', 'renderView', 'htmlTemplate', 'textTemplate']);
    }

    public function test_rule_is_off_when_prefix_is_empty(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], []);
    }
}
