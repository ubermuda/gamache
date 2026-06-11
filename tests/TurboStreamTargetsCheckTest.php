<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\TurboStreamTargetsCheck;
use PHPUnit\Framework\TestCase;

final class TurboStreamTargetsCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/TurboStreamTargetsCheck';
    }

    public function test_matching_targets_pass(): void
    {
        $check = new TurboStreamTargetsCheck();
        $check->run($this->fixtures.'/passing/templates/layout.html.twig');
        $check->run($this->fixtures.'/passing/templates/stream.html.twig');

        $result = $check->getResult();

        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_missing_target_is_reported(): void
    {
        $check = new TurboStreamTargetsCheck();
        $check->run($this->fixtures.'/bad/templates/layout.html.twig');
        $check->run($this->fixtures.'/bad/templates/stream.html.twig');

        $result = $check->getResult();

        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('non-existent-id', $result->violations[0]->message);
    }

    public function test_dynamic_targets_are_skipped(): void
    {
        $check = new TurboStreamTargetsCheck();

        $tmpFile = sys_get_temp_dir().'/turbo_test_'.uniqid().'.html.twig';
        file_put_contents($tmpFile, '<turbo-stream target="{{ id }}"><template></template></turbo-stream>');
        $check->run($tmpFile);
        unlink($tmpFile);

        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
    }
}
