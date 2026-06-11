<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\XlfPluralizationCheck;
use PHPUnit\Framework\TestCase;

final class XlfPluralizationCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/XlfPluralizationCheck';
    }

    public function test_plural_with_zero_case_passes(): void
    {
        $check = new XlfPluralizationCheck();
        $check->run($this->fixtures.'/passing/translations/messages.en.xlf');

        $result = $check->getResult();

        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_plural_missing_zero_case_is_reported(): void
    {
        $check = new XlfPluralizationCheck();
        $check->run($this->fixtures.'/bad/translations/messages.en.xlf');

        $result = $check->getResult();

        self::assertTrue($result->hasFailed());
        self::assertCount(2, $result->violations);
        self::assertStringContainsString('items.count.missing_zero', $result->violations[0]->message);
        self::assertStringContainsString('other.count.missing_zero', $result->violations[1]->message);
    }
}
