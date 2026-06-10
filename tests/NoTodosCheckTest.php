<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\NoTodosCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class NoTodosCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/NoTodosCheck';
    }

    public function test_passes_when_no_todos(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/passing/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_todo(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/with_todo/src/FooService.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('TODO', $result->violations[0]->message);
    }

    public function test_detects_fixme(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/with_fixme/src/FooService.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
    }

    public function test_detects_xxx(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/with_xxx/src/FooService.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
    }

    public function test_detects_at_todo(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/with_atodo/src/FooService.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
    }

    public function test_reports_correct_line_number(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/with_todo/src/FooService.php');
        $result = $check->getResult();
        self::assertSame(9, $result->violations[0]->line);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new NoTodosCheck();
        $check->run('/tmp/nonexistent-gamache/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_lowercase_todo_without_at_sign_is_not_flagged(): void
    {
        $check = new NoTodosCheck();
        $check->run($this->fixtures.'/case_sensitive/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
