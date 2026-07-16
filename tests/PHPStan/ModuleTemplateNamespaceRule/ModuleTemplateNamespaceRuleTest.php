<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ModuleTemplateNamespaceRule;

use Gamache\PHPStan\ModuleTemplateNamespaceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ModuleTemplateNamespaceRule>
 */
final class ModuleTemplateNamespaceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ModuleTemplateNamespaceRule('Module/', ['render', 'renderView', 'htmlTemplate', 'textTemplate']);
    }

    public function test_namespaced_dynamic_and_out_of_scope_references_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_module_path_literals_in_render_methods_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Template "Module/Event/show.html.twig" must be referenced through its Twig namespace: "@Event/show.html.twig".',
                11,
            ],
            [
                'Template "Module/Notification/email/invite.html.twig" must be referenced through its Twig namespace: "@Notification/email/invite.html.twig".',
                16,
            ],
        ]);
    }
}
