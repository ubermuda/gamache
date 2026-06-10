<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\ServiceTagNamesCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class ServiceTagNamesCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/ServiceTagNamesCheck';
    }

    public function test_passes_when_tags_use_app_prefix(): void
    {
        $check = new ServiceTagNamesCheck();
        $check->run($this->fixtures.'/passing/src/MyInterface.php');
        $check->run($this->fixtures.'/passing/config/services.yaml');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_bad_php_attribute_tag(): void
    {
        $check = new ServiceTagNamesCheck();
        $check->run($this->fixtures.'/bad_php_tag/src/MyInterface.php');
        $check->run($this->fixtures.'/bad_php_tag/config/services.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('kernel.event_listener', $result->violations[0]->message);
    }

    public function test_detects_bad_yaml_tag(): void
    {
        $check = new ServiceTagNamesCheck();
        $check->run($this->fixtures.'/bad_yaml_tag/config/services.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringEndsWith('config/services.yaml', $result->violations[0]->file);
    }

    public function test_returns_no_violations_when_no_files_fed(): void
    {
        $check = new ServiceTagNamesCheck();
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
