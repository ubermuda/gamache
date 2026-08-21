<?php

declare(strict_types=1);

namespace Gamache\Tests\PHPStan\ControllerTemplateNameRule;

use Gamache\PHPStan\ControllerTemplateNameRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<ControllerTemplateNameRule>
 */
final class ControllerTemplateNameRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ControllerTemplateNameRule(
            '#^App\\\\Module\\\\(.+)\\\\Controller\\\\[^\\\\]+Controller$#',
            'templates/Module',
            ['render', 'renderFormResponse'],
            __DIR__,
        );
    }

    public function test_matching_skipped_and_out_of_scope_controllers_pass(): void
    {
        $this->analyse([__DIR__.'/Fixture/valid.php'], []);
    }

    public function test_a_locale_segment_does_not_stop_a_template_matching(): void
    {
        // ShowTerms and ShowPrivacy resolve their template from the request
        // locale, so they can never name one literally. ShowCookies proves the
        // stripping is not a licence to ignore any dotted segment.
        $this->analyse([__DIR__.'/Fixture/locale.php'], [
            [
                'Controller ShowCookiesController renders a template but none under templates/Module/Legal matches its name (expected "show_cookies.html.twig"; matching ignores case, underscores, and directories).',
                31,
            ],
        ]);
    }

    public function test_controllers_without_matching_template_are_reported(): void
    {
        $this->analyse([__DIR__.'/Fixture/violation.php'], [
            [
                'Controller IssueBrainstormController renders a template but none under templates/Module/Project matches its name (expected "issue_brainstorm.html.twig"; matching ignores case, underscores, and directories).',
                8,
            ],
            [
                'Controller WorkspaceOverviewController renders a template but none under templates/Module/Project matches its name (expected "workspace_overview.html.twig"; matching ignores case, underscores, and directories).',
                17,
            ],
        ]);
    }
}
