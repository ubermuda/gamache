<?php

declare(strict_types=1);

namespace Gamache\Tests;

use Gamache\Check\DeploymentConfigParityCheck;
use Gamache\Check\Severity;
use PHPUnit\Framework\TestCase;

final class DeploymentConfigParityCheckTest extends TestCase
{
    private string $fixtures;

    protected function setUp(): void
    {
        $this->fixtures = __DIR__.'/Fixtures/DeploymentConfigParityCheck';
    }

    public function test_passes_when_every_terraform_variable_is_named_in_the_example(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/passing/terraform/variables.tf');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_terraform_variable_missing_from_the_example(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/missing_tfvar/terraform/variables.tf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('export_storage_key', $result->violations[0]->message);
        self::assertStringContainsString('terraform/variables.tf', $result->violations[0]->message);
        self::assertStringContainsString('terraform/terraform.tfvars.example', $result->violations[0]->message);
        self::assertStringEndsWith('terraform/terraform.tfvars.example', $result->violations[0]->file);
        self::assertNull($result->violations[0]->line);
    }

    public function test_a_variable_named_only_inside_a_longer_name_does_not_count(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/substring_only/terraform/variables.tf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('"region"', $result->violations[0]->message);
    }

    public function test_passes_when_every_documented_env_key_is_referenced_by_compose(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/passing/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_detects_env_key_the_compose_file_never_references(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/missing_env_key/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertSame(Severity::Error, $result->violations[0]->severity);
        self::assertStringContainsString('DEFAULT_URI', $result->violations[0]->message);
        self::assertStringContainsString('docker/compose/prod.env.example', $result->violations[0]->message);
        self::assertStringContainsString('docker/compose/prod.yaml', $result->violations[0]->message);
        self::assertStringEndsWith('docker/compose/prod.yaml', $result->violations[0]->file);
    }

    public function test_commented_out_env_assignments_are_still_expected_in_compose(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/commented_env_key/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('MAILER_FROM_NAME', $result->violations[0]->message);
    }

    public function test_passes_silently_when_the_counterpart_file_is_absent(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/no_counterpart/terraform/variables.tf');
        $check->run($this->fixtures.'/no_counterpart/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_returns_no_violations_when_file_absent(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run('/tmp/nonexistent-gamache/terraform/variables.tf');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_honours_configured_paths(): void
    {
        $check = new DeploymentConfigParityCheck(
            terraformVariablesPath: 'infra/vars.tf',
            terraformExamplePath: 'infra/example.tfvars',
        );
        self::assertContains('infra/vars.tf', $check->getTargetPatterns());

        $check->run($this->fixtures.'/custom_paths/infra/vars.tf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('cluster_name', $result->violations[0]->message);
        self::assertStringContainsString('infra/example.tfvars', $result->violations[0]->message);
    }

    public function test_ignored_names_are_exempt(): void
    {
        $check = new DeploymentConfigParityCheck(ignoredTerraformVariables: ['export_storage_key']);
        $check->run($this->fixtures.'/missing_tfvar/terraform/variables.tf');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_ignored_env_keys_are_exempt(): void
    {
        $check = new DeploymentConfigParityCheck(ignoredEnvKeys: ['DEFAULT_URI']);
        $check->run($this->fixtures.'/missing_env_key/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }
}
