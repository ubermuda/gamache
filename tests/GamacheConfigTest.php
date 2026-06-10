<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\ServicesYamlCheck;
use Gamache\Config\GamacheConfig;
use PHPUnit\Framework\TestCase;

final class GamacheConfigTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/GamacheConfig';
    }

    public function test_returns_empty_checks_when_no_config_file(): void
    {
        $config = GamacheConfig::fromFile('/tmp/nonexistent-gamache-root');
        self::assertSame([], $config->checks);
    }

    public function test_returns_registered_checks(): void
    {
        $config = GamacheConfig::fromFile($this->fixtures.'/with_config');
        self::assertCount(1, $config->checks);
        self::assertInstanceOf(ServicesYamlCheck::class, $config->checks[0]);
    }

    public function test_returns_empty_checks_when_config_file_returns_non_gamache_config(): void
    {
        $config = GamacheConfig::fromFile($this->fixtures.'/invalid_config');
        self::assertSame([], $config->checks);
    }
}
