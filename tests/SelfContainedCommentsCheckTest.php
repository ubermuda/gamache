<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\SelfContainedCommentsCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class SelfContainedCommentsCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/SelfContainedCommentsCheck';
    }

    public function test_passes_on_self_contained_comments(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run($this->fixtures.'/passing/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_a_task_reference_in_a_php_comment(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run($this->fixtures.'/with_task/src/FooService.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertSame(9, $result->violations[0]->line);
    }

    public function test_does_not_flag_a_reference_inside_a_string_literal(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run($this->fixtures.'/string_not_flagged/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_a_handoff_reference_in_a_twig_comment_only(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run($this->fixtures.'/with_twig_handoff/templates/foo.html.twig');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        // Only the {# #} comment is flagged, not the rendered "Task 16" text.
        self::assertCount(1, $result->violations);
        self::assertSame(3, $result->violations[0]->line);
    }

    public function test_respects_the_ignore_marker(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run($this->fixtures.'/ignore_marker/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new SelfContainedCommentsCheck();
        $check->run('/tmp/nonexistent-gamache/src/FooService.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
