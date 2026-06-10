<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\MessengerRoutingCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class MessengerRoutingCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/MessengerRoutingCheck';
    }

    public function test_passes_when_all_routed_classes_exist(): void
    {
        $check = new MessengerRoutingCheck();
        $check->run($this->fixtures.'/passing/config/packages/messenger.yaml');
        $check->run($this->fixtures.'/passing/src/Message/SendWelcomeEmail.php');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_missing_class(): void
    {
        $check = new MessengerRoutingCheck();
        $check->run($this->fixtures.'/missing_class/config/packages/messenger.yaml');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('Nonexistent', $result->violations[0]->message);
        self::assertStringEndsWith('messenger.yaml', $result->violations[0]->file);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new MessengerRoutingCheck();
        $check->run('/tmp/nonexistent-gamache/config/packages/messenger.yaml');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_ignores_non_app_namespace_fqcns(): void
    {
        $check = new MessengerRoutingCheck();
        $check->run($this->fixtures.'/passing/config/packages/messenger.yaml');
        $check->run($this->fixtures.'/passing/src/Message/SendWelcomeEmail.php');
        $result = $check->getResult();
        self::assertEmpty($result->violations);
    }
}
