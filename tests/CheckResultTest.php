<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\CheckResult;
use Gamache\Check\Severity;
use Gamache\Check\Violation;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    public function test_has_failed_returns_false_with_no_violations(): void
    {
        $result = new CheckResult('test');
        self::assertFalse($result->hasFailed());
    }

    public function test_has_failed_returns_true_when_error_severity_present(): void
    {
        $violation = new Violation('bad', Severity::Error, 'file.php', 1);
        $result = new CheckResult('test', [$violation]);
        self::assertTrue($result->hasFailed());
    }

    public function test_has_failed_returns_false_with_only_warnings(): void
    {
        $violation = new Violation('advisory', Severity::Warning, 'file.php', 1);
        $result = new CheckResult('test', [$violation]);
        self::assertFalse($result->hasFailed());
    }

    public function test_skipped_factory_produces_skipped_result(): void
    {
        $result = CheckResult::skipped('MyCheck');
        self::assertTrue($result->skipped);
        self::assertFalse($result->hasFailed());
        self::assertSame('MyCheck', $result->name);
        self::assertEmpty($result->violations);
    }
}
