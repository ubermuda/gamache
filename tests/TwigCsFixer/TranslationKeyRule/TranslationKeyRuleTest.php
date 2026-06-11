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
}
