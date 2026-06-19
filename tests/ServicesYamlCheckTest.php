<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\ServicesYamlCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class ServicesYamlCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/ServicesYamlCheck';
    }

    public function test_passes_when_services_yaml_is_clean(): void
    {
        $check = new ServicesYamlCheck();
        $check->run($this->fixtures.'/passing/config/services.yaml');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_instanceof_block(): void
    {
        $check = new ServicesYamlCheck();
        $check->run($this->fixtures.'/with_instanceof/config/services.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringEndsWith('config/services.yaml', $result->violations[0]->file);
        self::assertStringContainsString('_instanceof', $result->violations[0]->message);
        self::assertNull($result->violations[0]->line);
    }

    public function test_detects_arguments_block(): void
    {
        $check = new ServicesYamlCheck();
        $check->run($this->fixtures.'/with_arguments/config/services.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('arguments', $result->violations[0]->message);
        self::assertNull($result->violations[0]->line);
    }

    public function test_allows_arguments_block_on_third_party_services(): void
    {
        $check = new ServicesYamlCheck();
        $check->run($this->fixtures.'/with_third_party_arguments/config/services.yaml');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_arguments_block_on_app_class_with_non_app_id(): void
    {
        $check = new ServicesYamlCheck();
        $check->run($this->fixtures.'/with_app_class_arguments/config/services.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('arguments', $result->violations[0]->message);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new ServicesYamlCheck();
        $check->run('/tmp/nonexistent-gamache/config/services.yaml');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
