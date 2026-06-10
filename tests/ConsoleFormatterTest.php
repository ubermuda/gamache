<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\CheckResult;
use Gamache\Check\Severity;
use Gamache\Check\Violation;
use Gamache\Formatter\ConsoleFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ConsoleFormatterTest extends TestCase
{
    private ConsoleFormatter $formatter;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->formatter = new ConsoleFormatter();
        $this->output = new BufferedOutput();
    }

    public function test_passing_check_renders_green_tick(): void
    {
        $results = [new CheckResult('MyCheck')];
        $this->formatter->format($results, $this->output);
        $out = $this->output->fetch();
        self::assertStringContainsString('✔', $out);
        self::assertStringContainsString('MyCheck', $out);
    }

    public function test_failed_check_renders_red_cross(): void
    {
        $violation = new Violation('bad thing', Severity::Error, 'src/Foo.php', 42);
        $results = [new CheckResult('MyCheck', [$violation])];
        $this->formatter->format($results, $this->output);
        $out = $this->output->fetch();
        self::assertStringContainsString('✗', $out);
        self::assertStringContainsString('src/Foo.php:42', $out);
        self::assertStringContainsString('bad thing', $out);
    }

    public function test_advisory_check_renders_yellow_warning(): void
    {
        $violation = new Violation('advisory', Severity::Warning, 'src/Bar.php', 7);
        $results = [new CheckResult('MyCheck', [$violation])];
        $this->formatter->format($results, $this->output);
        $out = $this->output->fetch();
        self::assertStringContainsString('⚠', $out);
        self::assertStringContainsString('src/Bar.php:7', $out);
    }

    public function test_skipped_check_renders_dim(): void
    {
        $results = [CheckResult::skipped('MyCheck')];
        $this->formatter->format($results, $this->output);
        $out = $this->output->fetch();
        self::assertStringContainsString('–', $out);
        self::assertStringContainsString('(no matching files)', $out);
    }

    public function test_summary_line_counts_correctly(): void
    {
        $error = new Violation('e', Severity::Error, 'f.php', 1);
        $warning = new Violation('w', Severity::Warning, 'g.php', 2);
        $results = [
            new CheckResult('PassCheck'),
            new CheckResult('FailCheck', [$error]),
            new CheckResult('WarnCheck', [$warning]),
            CheckResult::skipped('SkippedCheck'),
        ];
        $this->formatter->format($results, $this->output);
        $out = $this->output->fetch();
        self::assertStringContainsString('1 passed · 1 failed · 1 advisory', $out);
    }
}
