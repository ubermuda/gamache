<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer\TranslationKeyRule;

use Gamache\TwigCsFixer\TranslationKeyRule;
use TwigCsFixer\Test\AbstractRuleTestCase;

final class TranslationKeyRuleTest extends AbstractRuleTestCase
{
    public function test_valid_trans_keys_produce_no_violations(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/valid_key.twig',
            false,
        );
    }

    public function test_natural_language_string_in_trans_is_flagged(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            ['TranslationKey.Error:1:4' => 'String "Welcome back" passed to |trans must be a translation key (e.g. "account.login.heading").'],
            __DIR__.'/Fixture/natural_language_trans.twig',
            false,
        );
    }

    public function test_html_entities_in_text_node_do_not_produce_false_positive(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/html_entity.twig',
            false,
        );
    }

    public function test_raw_text_node_is_flagged(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            ['TranslationKey.Error:1:1' => 'Raw text "Submit" found in template. Wrap it in a translation key and use |trans.'],
            __DIR__.'/Fixture/raw_text_node.twig',
            false,
        );
    }

    public function test_stimulus_action_descriptor_with_arrow_does_not_produce_false_positive(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/stimulus_action_descriptor.twig',
            false,
        );
    }

    public function test_trans_tag_with_a_valid_key_produces_no_violation(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/trans_tag_key.twig',
            false,
        );
    }

    public function test_trans_tag_with_params_and_a_valid_key_produces_no_violation(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/trans_tag_with_params.twig',
            false,
        );
    }

    public function test_natural_language_inside_trans_tag_is_flagged(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            ['TranslationKey.Error:1:12' => 'Content "Welcome back" inside {% trans %} must be a translation key (e.g. "account.login.heading").'],
            __DIR__.'/Fixture/trans_tag_natural_language.twig',
            false,
        );
    }

    public function test_trans_tag_with_a_from_domain_and_a_valid_key_produces_no_violation(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            [],
            __DIR__.'/Fixture/trans_tag_from_domain.twig',
            false,
        );
    }

    public function test_css_inside_style_element_is_not_flagged_but_text_after_it_is(): void
    {
        // The trailing <p> text must be flagged: this proves the <style> element is
        // both skipped AND correctly closed (otherwise the rest of the file would be
        // swallowed and nothing would be reported).
        $this->checkRule(
            new TranslationKeyRule(),
            ['TranslationKey.Error:1:1' => 'Raw text "Visible text after style" found in template. Wrap it in a translation key and use |trans.'],
            __DIR__.'/Fixture/style_block.twig',
            false,
        );
    }

    public function test_js_inside_script_element_is_not_flagged_but_text_after_it_is(): void
    {
        $this->checkRule(
            new TranslationKeyRule(),
            ['TranslationKey.Error:1:1' => 'Raw text "Visible text after script" found in template. Wrap it in a translation key and use |trans.'],
            __DIR__.'/Fixture/script_block.twig',
            false,
        );
    }

    public function test_excluded_paths_skip_the_whole_file(): void
    {
        $this->checkRule(
            new TranslationKeyRule(excludedPaths: ['*raw_text_node.twig']),
            [],
            __DIR__.'/Fixture/raw_text_node.twig',
            false,
        );
    }
}
