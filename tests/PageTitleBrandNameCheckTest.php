<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\PageTitleBrandNameCheck;
use PHPUnit\Framework\TestCase;

final class PageTitleBrandNameCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/PageTitleBrandNameCheck';
    }

    public function test_bare_page_titles_pass(): void
    {
        $check = new PageTitleBrandNameCheck();
        $check->run($this->fixtures.'/passing/translations/messages.en.xlf');

        $result = $check->getResult();

        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_brand_and_separator_in_page_titles_are_reported(): void
    {
        $check = new PageTitleBrandNameCheck();
        $check->run($this->fixtures.'/bad/translations/messages.en.xlf');

        $result = $check->getResult();

        self::assertTrue($result->hasFailed());
        self::assertCount(2, $result->violations);
        self::assertStringContainsString('home.page.title', $result->violations[0]->message);
        self::assertStringContainsString('login.page.title', $result->violations[1]->message);
    }
}
