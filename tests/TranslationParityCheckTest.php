<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\Severity;
use Gamache\Check\TranslationParityCheck;
use PHPUnit\Framework\TestCase;

final class TranslationParityCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/TranslationParityCheck';
    }

    public function test_passes_when_locales_are_in_sync(): void
    {
        $check = new TranslationParityCheck();
        $check->run($this->fixtures.'/in_sync/translations/messages.en.xlf');
        $check->run($this->fixtures.'/in_sync/translations/messages.fr.xlf');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_missing_key_in_fr_locale(): void
    {
        $check = new TranslationParityCheck();
        $check->run($this->fixtures.'/missing_key/translations/messages.en.xlf');
        $check->run($this->fixtures.'/missing_key/translations/messages.fr.xlf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('app.world', $result->violations[0]->message);
        self::assertStringContainsString('fr', $result->violations[0]->message);
        // Points to the EN file where the key exists
        self::assertStringContainsString('messages.en.xlf', $result->violations[0]->file);
        // Line number is > 0 (points to the trans-unit line)
        self::assertGreaterThan(0, $result->violations[0]->line);
    }

    public function test_returns_no_violations_with_single_locale(): void
    {
        $check = new TranslationParityCheck();
        $check->run($this->fixtures.'/single_locale/translations/messages.en.xlf');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_returns_no_violations_when_no_files_fed(): void
    {
        $check = new TranslationParityCheck();
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
