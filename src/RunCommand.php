<?php

declare(strict_types=1);

namespace Gamache;

use Gamache\Check\CheckInterface;
use Gamache\Check\CheckResult;
use Gamache\Check\Violation;
use Gamache\Config\GamacheConfig;
use Gamache\Formatter\ConsoleFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RunCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
        parent::__construct('run');
    }

    protected function configure(): void
    {
        $this->addOption(
            'format',
            null,
            InputOption::VALUE_REQUIRED,
            'Output format: console (others are future work)', // @translation-check-ignore
            'console',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if ('console' !== $format) {
            $output->writeln(sprintf(
                '<error>Unknown format "%s". Only "console" is currently supported.</error>',
                $format,
            ));

            return Command::FAILURE;
        }

        $config = GamacheConfig::fromFile($this->projectRoot);
        $formatter = new ConsoleFormatter();
        $checks = $config->checks;

        // Collect patterns → checks mapping (deduplicated across checks)
        /** @var array<string, list<CheckInterface>> $patternToChecks */
        $patternToChecks = [];
        foreach ($checks as $check) {
            foreach ($check->getTargetPatterns() as $pattern) {
                $patternToChecks[$pattern][] = $check;
            }
        }

        // Enumerate all unique matching files across all patterns
        /** @var array<string, true> $allFiles absPath => true */
        $allFiles = [];
        foreach (array_keys($patternToChecks) as $pattern) {
            foreach ($this->resolvePattern($pattern) as $absPath) {
                $allFiles[$absPath] = true;
            }
        }

        // Dispatch each file to every check whose pattern matches it
        /** @var array<string, true> $checkHadFiles checkName => true */
        $checkHadFiles = [];
        foreach (array_keys($allFiles) as $absPath) {
            $relPath = substr($absPath, \strlen($this->projectRoot) + 1);
            /** @var array<string, true> $calledThisFile checkName => true */
            $calledThisFile = [];
            foreach ($patternToChecks as $pattern => $patternChecks) {
                if (!$this->matchesPattern($relPath, $pattern)) {
                    continue;
                }
                foreach ($patternChecks as $check) {
                    $name = $check->getName();
                    if (isset($calledThisFile[$name])) {
                        continue; // prevent double-dispatch if two patterns match same file
                    }
                    $calledThisFile[$name] = true;
                    $checkHadFiles[$name] = true;
                    $check->run($absPath);
                }
            }
        }

        // Collect results, relativizing violation paths
        $results = [];
        foreach ($checks as $check) {
            if (!isset($checkHadFiles[$check->getName()])) {
                $results[] = CheckResult::skipped($check->getName());
            } else {
                $results[] = $this->relativize($check->getResult());
            }
        }

        $formatter->format($results, $output);

        foreach ($results as $result) {
            if ($result->hasFailed()) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /** @return list<string> Absolute paths of files matching the pattern */
    private function resolvePattern(string $pattern): array
    {
        if (!str_contains($pattern, '*')) {
            $absPath = $this->projectRoot.'/'.$pattern;

            return file_exists($absPath) ? [$absPath] : [];
        }

        if (str_contains($pattern, '**')) {
            $base = rtrim(substr($pattern, 0, (int) strpos($pattern, '**')), '/');
            $absBase = $this->projectRoot.'/'.$base;
            if (!is_dir($absBase)) {
                return [];
            }
            $result = [];
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absBase, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iter as $file) {
                assert($file instanceof \SplFileInfo);
                $absPath = $file->getPathname();
                $relPath = substr($absPath, \strlen($this->projectRoot) + 1);
                if ($this->matchesPattern($relPath, $pattern)) {
                    $result[] = $absPath;
                }
            }

            return $result;
        }

        // Simple glob (e.g. translations/messages.*.xlf)
        return glob($this->projectRoot.'/'.$pattern) ?: [];
    }

    private function matchesPattern(string $relPath, string $pattern): bool
    {
        if (!str_contains($pattern, '*')) {
            return $relPath === $pattern;
        }

        return (bool) fnmatch($pattern, $relPath);
    }

    private function relativize(CheckResult $result): CheckResult
    {
        if ([] === $result->violations) {
            return $result;
        }

        $prefix = $this->projectRoot.'/';

        return new CheckResult(
            $result->name,
            array_map(
                fn (Violation $v) => new Violation(
                    $v->message,
                    $v->severity,
                    str_starts_with($v->file, $prefix) ? substr($v->file, \strlen($prefix)) : $v->file,
                    $v->line,
                ),
                $result->violations,
            ),
            $result->skipped,
        );
    }
}
