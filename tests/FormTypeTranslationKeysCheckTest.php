<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\FormTypeTranslationKeysCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class FormTypeTranslationKeysCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/FormTypeTranslationKeysCheck';
    }

    public function test_passes_when_key_matches_required_prefix(): void
    {
        $check = new FormTypeTranslationKeysCheck();
        $check->run($this->fixtures.'/passing/src/Module/GitHub/Form/ImportGitHubRepoFormType.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_wrong_key_prefix(): void
    {
        $check = new FormTypeTranslationKeysCheck();
        $check->run($this->fixtures.'/bad_prefix/src/Module/Project/Form/CreateProjectFormType.php');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('project.form.create_project_form.', $result->violations[0]->message);
    }

    public function test_returns_no_violations_when_no_files_fed(): void
    {
        $check = new FormTypeTranslationKeysCheck();
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
