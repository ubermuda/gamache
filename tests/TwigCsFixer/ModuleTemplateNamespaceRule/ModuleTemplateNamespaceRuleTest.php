<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\ModuleTemplateNamespaceRule;

use Gamache\TwigCsFixer\ModuleTemplateNamespaceRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class ModuleTemplateNamespaceRuleTest extends AbstractRuleTestCase
{
    public function test_namespaced_and_out_of_scope_references_pass(): void
    {
        $this->checkRule(
            new ModuleTemplateNamespaceRule(),
            [],
            __DIR__.'/Fixture/valid.twig',
            false,
        );
    }

    public function test_module_path_literals_are_flagged(): void
    {
        $this->checkRule(
            new ModuleTemplateNamespaceRule(),
            [
                'ModuleTemplateNamespace.Error:1:12' => 'Template "Module/Event/show.html.twig" must be referenced through its Twig namespace: "@Event/show.html.twig".',
                'ModuleTemplateNamespace.Error:2:12' => 'Template "Module/Rsvp/_card.html.twig" must be referenced through its Twig namespace: "@Rsvp/_card.html.twig".',
                'ModuleTemplateNamespace.Error:3:11' => 'Template "Module/Design/macros.html.twig" must be referenced through its Twig namespace: "@Design/macros.html.twig".',
            ],
            __DIR__.'/Fixture/violation.twig',
            false,
        );
    }
}
