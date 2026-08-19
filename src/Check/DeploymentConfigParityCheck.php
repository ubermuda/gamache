<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * A deployment variable can be declared and consumed while never reaching the
 * file an operator actually copies. `terraform validate` passes either way,
 * because the example tfvars file is comment-only and nothing reads it; Compose
 * passes too, because an env file supplies interpolation values without
 * injecting them into containers. The option is then real but undiscoverable.
 */
final class DeploymentConfigParityCheck extends AbstractCheck
{
    /**
     * @param string       $terraformVariablesPath    Where Terraform variables are declared.
     * @param string       $terraformExamplePath      The tfvars template an operator copies.
     *                                                Every declared variable must be named in it,
     *                                                inside a comment or not.
     * @param string       $composeEnvExamplePath     The env-file template an operator copies.
     * @param string       $composeFilePath           The Compose file that must reference every
     *                                                key the template documents.
     * @param list<string> $ignoredTerraformVariables Variable names exempt from the tfvars
     *                                                template, e.g. credentials a project
     *                                                supplies through `TF_VAR_*` only.
     * @param list<string> $ignoredEnvKeys            Env keys exempt from the Compose file.
     */
    public function __construct(
        private readonly string $terraformVariablesPath = 'terraform/variables.tf',
        private readonly string $terraformExamplePath = 'terraform/terraform.tfvars.example',
        private readonly string $composeEnvExamplePath = 'docker/compose/prod.env.example',
        private readonly string $composeFilePath = 'docker/compose/prod.yaml',
        private readonly array $ignoredTerraformVariables = [],
        private readonly array $ignoredEnvKeys = [],
    ) {
    }

    public function getName(): string
    {
        return 'DeploymentConfigParityCheck';
    }

    public function getTargetPatterns(): array
    {
        return [$this->terraformVariablesPath, $this->composeEnvExamplePath];
    }

    public function run(string $absPath): void
    {
        $terraformRoot = $this->projectRoot($absPath, $this->terraformVariablesPath);
        if (null !== $terraformRoot) {
            $this->compare(
                $terraformRoot.'/'.$this->terraformExamplePath,
                self::parseTerraformVariables($this->read($absPath) ?? ''),
                $this->ignoredTerraformVariables,
                fn (string $name): string => sprintf( // @translation-check-ignore
                    'Terraform variable "%s" is declared in %s but never named in %s, so an operator copying the template cannot discover it. A commented-out example counts.',
                    $name,
                    $this->terraformVariablesPath,
                    $this->terraformExamplePath,
                ),
            );
        }

        $composeRoot = $this->projectRoot($absPath, $this->composeEnvExamplePath);
        if (null !== $composeRoot) {
            $this->compare(
                $composeRoot.'/'.$this->composeFilePath,
                self::parseEnvKeys($this->read($absPath) ?? ''),
                $this->ignoredEnvKeys,
                fn (string $name): string => sprintf( // @translation-check-ignore
                    'Environment variable "%s" is documented in %s but never referenced in %s. A Compose env file supplies interpolation values only, so setting it has no effect.',
                    $name,
                    $this->composeEnvExamplePath,
                    $this->composeFilePath,
                ),
            );
        }
    }

    /**
     * @param list<string>            $names
     * @param list<string>            $ignored
     * @param \Closure(string): string $message
     */
    private function compare(
        string $expectedInAbsPath,
        array $names,
        array $ignored,
        \Closure $message,
    ): void {
        // A project with no Terraform directory, or no Compose deployment, is not
        // in violation of anything — it simply does not run this half of the check.
        if (!is_file($expectedInAbsPath)) {
            return;
        }

        // An empty-but-readable template documents nothing, which is the failure
        // mode at its worst: every name must be reported, not silently forgiven.
        $haystack = $this->read($expectedInAbsPath);
        if (null === $haystack) {
            return;
        }

        foreach ($names as $name) {
            if (\in_array($name, $ignored, true)) {
                continue;
            }

            if (self::mentions($haystack, $name)) {
                continue;
            }

            $this->violations[] = new Violation(
                $message($name),
                Severity::Error,
                $expectedInAbsPath,
            );
        }
    }

    /**
     * The project root, when $absPath is $relPath resolved against it. Null when
     * the file being dispatched is the other half of the check.
     */
    private function projectRoot(string $absPath, string $relPath): ?string
    {
        $suffix = '/'.$relPath;

        return str_ends_with($absPath, $suffix)
            ? substr($absPath, 0, -\strlen($suffix))
            : null;
    }

    private function read(string $absPath): ?string
    {
        $content = @file_get_contents($absPath);

        return false === $content ? null : $content;
    }

    /**
     * Matches the whole name only: searching for `region` in a file that mentions
     * `db_cluster_region` alone must report the variable as missing.
     */
    private static function mentions(string $haystack, string $name): bool
    {
        return 1 === preg_match(
            '/(?<![A-Za-z0-9_])'.preg_quote($name, '/').'(?![A-Za-z0-9_])/',
            $haystack,
        );
    }

    /** @return list<string> */
    private static function parseTerraformVariables(string $content): array
    {
        preg_match_all('/^\s*variable\s+"([A-Za-z0-9_-]+)"\s*\{/m', $content, $matches);

        return self::unique($matches[1]);
    }

    /**
     * Commented-out assignments count: an optional variable an operator uncomments
     * is just as broken as a required one when the Compose file ignores it.
     *
     * @return list<string>
     */
    private static function parseEnvKeys(string $content): array
    {
        preg_match_all(
            '/^[ \t]*(?:#[ \t]*)?(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=/m',
            $content,
            $matches,
        );

        return self::unique($matches[1]);
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private static function unique(array $names): array
    {
        return array_values(array_unique($names));
    }
}
