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

    public function test_an_empty_template_documents_nothing_and_reports_every_variable(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/empty_example/terraform/variables.tf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('"region"', $result->violations[0]->message);
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

    public function test_detects_an_app_variable_that_reaches_no_deployment(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/app_unwired/.env');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(2, $result->violations);
        foreach ($result->violations as $violation) {
            self::assertStringContainsString('BRAND_NEW_TOKEN', $violation->message);
        }
        self::assertStringContainsString('terraform/', $result->violations[0]->message);
        self::assertStringContainsString('prod.yaml', $result->violations[1]->message);
    }

    public function test_a_variable_description_naming_the_env_key_is_not_wiring(): void
    {
        // app_unwired/terraform/variables.tf names BRAND_NEW_TOKEN in a
        // description, which a whole-file text search would accept.
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/app_unwired/.env');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertStringContainsString('BRAND_NEW_TOKEN', $result->violations[0]->message);
        self::assertStringContainsString('assigned by nothing', $result->violations[0]->message);
    }

    public function test_reads_app_variables_from_placeholders_a_dotenv_never_lists(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/app_placeholder/.env');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());

        $reported = array_unique(array_map(
            static fn ($violation): string => preg_replace('/^\D*"([A-Z_]+)".*$/', '$1', $violation->message) ?? '',
            $result->violations,
        ));
        sort($reported);
        self::assertSame(['STRIPE_SECRET_KEY', 'TRUSTED_PROXIES'], $reported);
    }

    public function test_module_provided_and_ignored_app_variables_are_excused(): void
    {
        $check = new DeploymentConfigParityCheck(
            moduleProvidedEnvKeys: ['MESSENGER_TRANSPORT_DSN'],
            ignoredAppEnvKeys: ['MESSENGER_TRANSPORT_DSN', 'COMPOSE_PROJECT_NAME'],
        );
        $check->run($this->fixtures.'/app_wired/.env');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
        self::assertEmpty($result->violations);
    }

    public function test_a_commented_out_dotenv_hint_is_not_demanded_of_a_deployment(): void
    {
        $check = new DeploymentConfigParityCheck(
            moduleProvidedEnvKeys: ['MESSENGER_TRANSPORT_DSN'],
            ignoredAppEnvKeys: ['MESSENGER_TRANSPORT_DSN', 'COMPOSE_PROJECT_NAME'],
        );
        $check->run($this->fixtures.'/app_wired/.env');
        $result = $check->getResult();
        self::assertFalse($result->hasFailed());
    }

    public function test_detects_a_stale_exemption_the_app_no_longer_reads(): void
    {
        $check = new DeploymentConfigParityCheck(
            ignoredAppEnvKeys: ['MESSENGER_TRANSPORT_DSN', 'COMPOSE_PROJECT_NAME', 'GONE_LONG_AGO'],
        );
        $check->run($this->fixtures.'/app_wired/.env');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('GONE_LONG_AGO', $result->violations[0]->message);
        self::assertStringContainsString('stale', $result->violations[0]->message);
        self::assertStringEndsWith('.env', $result->violations[0]->file);
    }

    public function test_detects_a_compose_knob_the_template_never_offers(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/compose_undocumented/docker/compose/prod.env.example');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(2, $result->violations);

        $reported = array_map(
            static fn ($violation): string => preg_replace('/^\D*"([A-Z_]+)".*$/', '$1', $violation->message) ?? '',
            $result->violations,
        );
        sort($reported);
        // APP_ENV is a literal, so it is not settable and not reported.
        self::assertSame(['APP_SOURCE_URL', 'EXPORT_STORAGE_PREFIX'], $reported);
        self::assertStringEndsWith('prod.env.example', $result->violations[0]->file);
    }

    public function test_detects_a_tfvars_entry_no_variable_backs(): void
    {
        $check = new DeploymentConfigParityCheck();
        $check->run($this->fixtures.'/tfvar_undeclared/terraform/variables.tf');
        $result = $check->getResult();
        self::assertTrue($result->hasFailed());
        self::assertCount(1, $result->violations);
        self::assertStringContainsString('db_cluster_size', $result->violations[0]->message);
        self::assertStringContainsString('does nothing', $result->violations[0]->message);
        self::assertStringEndsWith('terraform/variables.tf', $result->violations[0]->file);
    }
}
