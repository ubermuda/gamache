<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\CommentBudgetCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class CommentBudgetCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/CommentBudgetCheck';
    }

    public function test_passes_when_blocks_are_within_budget(): void
    {
        $result = $this->check('passing');
        self::assertEmpty($result->violations);
    }

    public function test_detects_a_block_over_budget(): void
    {
        $result = $this->check('long_block');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('7 lines', $result->violations[0]->message);
    }

    public function test_a_blank_line_does_not_break_a_block(): void
    {
        $result = $this->check('long_block');
        self::assertSame(11, $result->violations[0]->line);
    }

    public function test_violations_are_advisory(): void
    {
        $result = $this->check('long_block');
        self::assertSame(Severity::Warning, $result->violations[0]->severity);
        self::assertFalse($result->hasFailed());
    }

    public function test_ignore_marker_suppresses_the_block(): void
    {
        $result = $this->check('ignore_marker');
        self::assertEmpty($result->violations);
    }

    public function test_docblocks_are_not_counted(): void
    {
        $result = $this->check('docblock');
        self::assertEmpty($result->violations);
    }

    public function test_blocks_separated_by_code_are_counted_separately(): void
    {
        $result = $this->check('split_by_code');
        self::assertEmpty($result->violations);
    }

    public function test_budget_is_configurable(): void
    {
        $check = new CommentBudgetCheck(maxLines: 2);
        $check->run($this->fixtures.'/split_by_code/src/FooService.php');
        self::assertCount(2, $check->getResult()->violations);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new CommentBudgetCheck();
        $check->run('/tmp/nonexistent-gamache/src/FooService.php');
        self::assertEmpty($check->getResult()->violations);
    }

    public function test_detects_a_long_yaml_comment(): void
    {
        $result = $this->checkFile('syntax/config/long.yaml');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('6 lines', $result->violations[0]->message);
        self::assertSame(1, $result->violations[0]->line);
    }

    public function test_a_shebang_does_not_start_a_block(): void
    {
        $result = $this->checkFile('syntax/config/shebang.yaml');
        self::assertEmpty($result->violations);
    }

    public function test_detects_long_javascript_line_and_block_comments(): void
    {
        $result = $this->checkFile('syntax/assets/long.js');
        self::assertCount(2, $result->violations);
        self::assertStringContainsString('6 lines', $result->violations[0]->message);
        self::assertStringContainsString('7 lines', $result->violations[1]->message);
        self::assertSame(21, $result->violations[1]->line);
    }

    public function test_jsdoc_blocks_are_exempt(): void
    {
        $result = $this->checkFile('syntax/assets/long.js');
        $lines = array_map(fn ($v) => $v->line, $result->violations);
        self::assertNotContains(9, $lines);
    }

    public function test_detects_a_long_twig_comment(): void
    {
        $result = $this->checkFile('syntax/templates/long.twig');
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('6 lines', $result->violations[0]->message);
    }

    public function test_dependency_directories_are_skipped(): void
    {
        $result = $this->checkFile('syntax/node_modules/pkg/long.js');
        self::assertEmpty($result->violations);
    }

    private function check(string $fixture): \Gamache\Check\CheckResult
    {
        return $this->checkFile($fixture.'/src/FooService.php');
    }

    private function checkFile(string $relative): \Gamache\Check\CheckResult
    {
        $check = new CommentBudgetCheck();
        $check->run($this->fixtures.'/'.$relative);

        return $check->getResult();
    }
}
