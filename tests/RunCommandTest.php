<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\RunCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunCommandTest extends TestCase
{
    public function test_exits_success_when_no_checks_fail(): void
    {
        // Use a project root with no files at all — all checks are skipped
        $command = new RunCommand('/tmp/nonexistent-gamache-root');
        $tester = new CommandTester($command);
        $tester->execute([]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('0 passed · 0 failed · 0 advisory', $tester->getDisplay());
    }

    public function test_exits_failure_for_unknown_format(): void
    {
        $command = new RunCommand('/tmp/nonexistent-gamache-root');
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'json']);
        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Unknown format', $tester->getDisplay());
    }

    public function test_skips_check_when_no_matching_files(): void
    {
        // Project root has a gamache.php listing checks, but no source files matching their patterns
        $fixtureRoot = __DIR__.'/Fixtures/RunCommand/with_checks';
        $command = new RunCommand($fixtureRoot);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $out = $tester->getDisplay();
        self::assertStringContainsString('(no matching files)', $out);
    }
}
