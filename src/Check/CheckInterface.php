<?php

declare(strict_types=1);

namespace Gamache\Check;

interface CheckInterface
{
    public function getName(): string;

    /**
     * Glob patterns (relative to project root) this check inspects.
     * RunCommand uses these to enumerate files and dispatch them here.
     *
     * @return list<string>
     */
    public function getTargetPatterns(): array;

    /**
     * Called once per file whose path matches one of getTargetPatterns().
     * Violation file paths are absolute; RunCommand relativizes them before display.
     */
    public function run(string $absPath): void;

    /**
     * Called after all matching files have been dispatched.
     * Checks that process files independently return accumulated violations.
     * Checks that need cross-file analysis perform that analysis here.
     */
    public function getResult(): CheckResult;
}
