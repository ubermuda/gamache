<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\NoArbitraryValuesCheck;
use PHPUnit\Framework\TestCase;

final class NoArbitraryValuesCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/NoArbitraryValuesCheck';
    }

    public function test_passes_when_no_arbitrary_values(): void
    {
        $check = new NoArbitraryValuesCheck();
        $check->run($this->fixtures.'/passing/templates/page.html.twig');
        $check->run($this->fixtures.'/passing/assets/styles/app.css');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_arbitrary_value_in_twig(): void
    {
        $check = new NoArbitraryValuesCheck();
        $check->run($this->fixtures.'/bad_twig/templates/page.html.twig');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('templates/', $result->violations[0]->file);
    }

    public function test_detects_arbitrary_value_in_js(): void
    {
        $check = new NoArbitraryValuesCheck();
        $check->run($this->fixtures.'/bad_js/assets/app.js');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('assets/', $result->violations[0]->file);
    }

    public function test_detects_numeric_arbitrary_value_in_css(): void
    {
        $check = new NoArbitraryValuesCheck();
        $check->run($this->fixtures.'/bad_css/assets/styles/app.css');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringEndsWith('assets/styles/app.css', $result->violations[0]->file);
    }

    public function test_structural_brackets_in_css_are_not_flagged(): void
    {
        $check = new NoArbitraryValuesCheck();
        $check->run($this->fixtures.'/passing/assets/styles/app.css');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }

    public function test_ignored_file_is_not_scanned(): void
    {
        $check = new NoArbitraryValuesCheck(ignoredFiles: ['assets/styles/app.css']);
        $check->run($this->fixtures.'/ignored/assets/styles/app.css');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
