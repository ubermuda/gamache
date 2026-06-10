<?php

declare(strict_types=1);

namespace Gamache\Formatter;

use Gamache\Check\Severity;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ConsoleFormatter implements FormatterInterface
{
    public function format(array $results, OutputInterface $output): void
    {
        $passed = 0;
        $failed = 0;
        $advisory = 0;

        foreach ($results as $result) {
            if ($result->skipped) {
                $output->writeln(sprintf('  <fg=gray>–  %s  (no matching files)</>', $result->name));
                continue;
            }

            if ($result->hasFailed()) {
                ++$failed;
                $output->writeln(sprintf('  <fg=red>✗  %s</>', $result->name));
                foreach ($result->violations as $violation) {
                    $output->writeln(null !== $violation->line
                        ? sprintf('       %s:%d', $violation->file, $violation->line)
                        : sprintf('       %s', $violation->file));
                    $output->writeln(sprintf('         %s', $violation->message));
                }
                continue;
            }

            $hasWarnings = array_any(
                $result->violations,
                fn ($v) => Severity::Warning === $v->severity,
            );

            if ($hasWarnings) {
                ++$advisory;
                $output->writeln(sprintf('  <fg=yellow>⚠  %s</>', $result->name));
                foreach ($result->violations as $violation) {
                    $output->writeln(null !== $violation->line
                        ? sprintf('       %s:%d', $violation->file, $violation->line)
                        : sprintf('       %s', $violation->file));
                    $output->writeln(sprintf('         %s', $violation->message));
                }
                continue;
            }

            ++$passed;
            $output->writeln(sprintf('  <fg=green>✔  %s</>', $result->name));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '  %d passed · %d failed · %d advisory',
            $passed,
            $failed,
            $advisory,
        ));
    }
}
