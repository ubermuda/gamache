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

    public function test_suppressed_string_is_ignored(): void
    {
        $check = new TranslationCheck();
        $check->run($this->fixtures.'/suppressed/src/Service/WelcomeService.php');
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
}
