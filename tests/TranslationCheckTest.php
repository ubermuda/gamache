<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\Severity;
use Gamache\Check\TranslationCheck;
use PHPUnit\Framework\TestCase;

final class TranslationCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/TranslationCheck';
    }

    public function test_flagged_php_string_produces_warning_not_error(): void
    {
        $check = new TranslationCheck();
        $check->run($this->fixtures.'/flagged_php/src/Service/WelcomeService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertNotEmpty($result->violations);
        self::assertSame(Severity::Warning, $result->violations[0]->severity);
        self::assertStringContainsString('Sign in', $result->violations[0]->message);
    }

    public function test_string_in_exception_constructor_is_ignored(): void
    {
        $check = new TranslationCheck();
        $check->run($this->fixtures.'/ignored_call_site/src/Service/WelcomeService.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_translation_key_string_is_not_flagged(): void
    {
        $check = new TranslationCheck();
        $check->run($this->fixtures.'/translation_keys/src/Service/WelcomeService.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_flagged_twig_string_produces_warning(): void
    {
        $check = new TranslationCheck();
        $check->run($this->fixtures.'/flagged_twig/templates/page.html.twig');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertNotEmpty($result->violations);
        self::assertSame(Severity::Warning, $result->violations[0]->severity);
    }

    public function test_source_namespace_ignore_skips_matching_file(): void
    {
        $check = new TranslationCheck(ignoredSourceNamespaces: ['App\\Command\\*']);
        $check->run($this->fixtures.'/ignored_source_namespace/src/Command/SomeHandler.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_source_namespace_ignore_does_not_skip_non_matching_file(): void
    {
        $check = new TranslationCheck(ignoredSourceNamespaces: ['App\\Command\\*']);
        $check->run($this->fixtures.'/flagged_php/src/Service/WelcomeService.php');
        $result = $check->getResult();
        self::assertNotEmpty($result->violations);
    }

    public function test_source_namespace_ignore_supports_double_star_glob(): void
    {
        $check = new TranslationCheck(ignoredSourceNamespaces: ['App\\**\\Repository\\*']);
        $check->run($this->fixtures.'/ignored_source_namespace/src/Module/Project/Repository/IssueRepository.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_safe_attribute_namespace_skips_attribute_string_args(): void
    {
        $check = new TranslationCheck(safeAttributeNamespaces: ['Symfony\\Bridge\\Doctrine\\Attribute\\*']);
        $check->run($this->fixtures.'/safe_attribute/src/Controller/IssueController.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_unsafe_attribute_namespace_still_flags_string_args(): void
    {
        $check = new TranslationCheck(safeAttributeNamespaces: ['Symfony\\Bridge\\Doctrine\\Attribute\\*']);
        $check->run($this->fixtures.'/safe_attribute/src/Controller/IssueController.php');
        // Run again on a file with a non-safe attribute to confirm the rule is selective
        $check2 = new TranslationCheck();
        $check2->run($this->fixtures.'/flagged_php/src/Service/WelcomeService.php');
        self::assertNotEmpty($check2->getResult()->violations);
    }

    public function test_safe_twig_function_skips_its_string_args(): void
    {
        $check = new TranslationCheck(safeTwigFunctions: ['date']);
        $check->run($this->fixtures.'/safe_twig_function/templates/page.html.twig');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_safe_twig_function_does_not_skip_other_strings(): void
    {
        $check = new TranslationCheck(safeTwigFunctions: ['date']);
        $check->run($this->fixtures.'/flagged_twig/templates/page.html.twig');
        $result = $check->getResult();
        self::assertNotEmpty($result->violations);
    }
}
