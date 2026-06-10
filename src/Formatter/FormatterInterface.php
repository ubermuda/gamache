<?php

declare(strict_types=1);

namespace Gamache\Formatter;

use Gamache\Check\CheckResult;
use Symfony\Component\Console\Output\OutputInterface;

interface FormatterInterface
{
    /** @param list<CheckResult> $results */
    public function format(array $results, OutputInterface $output): void;
}
