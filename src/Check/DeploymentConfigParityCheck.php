<?php

declare(strict_types=1);

namespace Gamache\Check;

/**
 * A deployment variable can be declared and consumed while never reaching the
 * file an operator actually copies. `terraform validate` passes either way,
 * because the example tfvars file is comment-only and nothing reads it; Compose
 * passes too, because an env file supplies interpolation values without
 * injecting them into containers. The option is then real but undiscoverable.
 *
 * The same break happens one step earlier, and silently: a variable the
 * application reads can reach no deployment at all. Nothing downstream notices,
 * because every file that would have mentioned it is simply consistent without
 * it.
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
     * @param string       $appEnvPath                The committed dotenv listing what the app reads.
     *                                                Absent disables the application-side scans.
     * @param list<string> $envReferenceDirs          Directories searched for `%env(...)%`
     *                                                placeholders, which a dotenv need not repeat.
     * @param string       $terraformDir              Searched as a whole for each variable the app
     *                                                reads; a name mentioned nowhere in it reaches
     *                                                no Terraform deployment.
     * @param string       $terraformReportPath       Where to report a variable missing from
     *                                                Terraform, i.e. the file wiring env is in.
     * @param list<string> $moduleProvidedEnvKeys     Injected by an external Terraform module, so
     *                                                absent from this repository's own `.tf` files
     *                                                by design. Transcribe from the pinned module.
     * @param list<string> $ignoredAppEnvKeys         Read by the app but deliberately reaching no
     *                                                deployment: development-only, or already
     *                                                correct at the committed dotenv value.
     */
    public function __construct(
        private readonly string $terraformVariablesPath = 'terraform/variables.tf',
        private readonly string $terraformExamplePath = 'terraform/terraform.tfvars.example',
        private readonly string $composeEnvExamplePath = 'docker/compose/prod.env.example',
        private readonly string $composeFilePath = 'docker/compose/prod.yaml',
        private readonly array $ignoredTerraformVariables = [],
        private readonly array $ignoredEnvKeys = [],
        private readonly string $appEnvPath = '.env',
        private readonly array $envReferenceDirs = ['config', 'src'],
        private readonly string $terraformDir = 'terraform',
        private readonly string $terraformReportPath = 'terraform/main.tf',
        private readonly array $moduleProvidedEnvKeys = [],
        private readonly array $ignoredAppEnvKeys = [],
    ) {
    }

    public function getName(): string
    {
        return 'DeploymentConfigParityCheck';
    }

    public function getTargetPatterns(): array
    {
        return [$this->terraformVariablesPath, $this->composeEnvExamplePath, $this->appEnvPath];
    }

    public function run(string $absPath): void
    {
        $terraformRoot = $this->projectRoot($absPath, $this->terraformVariablesPath);
        if (null !== $terraformRoot) {
            $this->runTerraformTemplateScans($absPath, $terraformRoot);
        }

        $composeRoot = $this->projectRoot($absPath, $this->composeEnvExamplePath);
        if (null !== $composeRoot) {
            $this->runComposeTemplateScans($absPath, $composeRoot);
        }

        $appRoot = $this->projectRoot($absPath, $this->appEnvPath);
        if (null !== $appRoot) {
            $this->runApplicationScans($absPath, $appRoot);
        }
    }

    private function runTerraformTemplateScans(string $absPath, string $root): void
    {
        $examplePath = $root.'/'.$this->terraformExamplePath;

        $this->compare(
            $examplePath,
            self::parseTerraformVariables($this->read($absPath) ?? ''),
            $this->ignoredTerraformVariables,
            fn (string $name): string => sprintf( // @translation-check-ignore
                'Terraform variable "%s" is declared in %s but never named in %s, so an operator copying the template cannot discover it. A commented-out example counts.',
                $name,
                $this->terraformVariablesPath,
                $this->terraformExamplePath,
            ),
        );

        // The reverse: a template entry no variable backs is a knob an operator
        // sets and Terraform ignores, which reads exactly like a working setting.
        $this->compare(
            $absPath,
            self::parseTerraformAssignments($this->read($examplePath) ?? ''),
            $this->ignoredTerraformVariables,
            fn (string $name): string => sprintf( // @translation-check-ignore
                'Terraform variable "%s" is offered by %s but never declared in %s, so setting it does nothing.',
                $name,
                $this->terraformExamplePath,
                $this->terraformVariablesPath,
            ),
        );
    }

    private function runComposeTemplateScans(string $absPath, string $root): void
    {
        $composePath = $root.'/'.$this->composeFilePath;

        $this->compare(
            $composePath,
            self::parseEnvKeys($this->read($absPath) ?? ''),
            $this->ignoredEnvKeys,
            fn (string $name): string => sprintf( // @translation-check-ignore
                'Environment variable "%s" is documented in %s but never referenced in %s. A Compose env file supplies interpolation values only, so setting it has no effect.',
                $name,
                $this->composeEnvExamplePath,
                $this->composeFilePath,
            ),
        );

        // The reverse: a knob the Compose file reads but the template never
        // offers is real and undiscoverable, which is this check's whole subject.
        $this->compare(
            $absPath,
            self::parseComposeSettable($this->read($composePath) ?? ''),
            $this->ignoredEnvKeys,
            fn (string $name): string => sprintf( // @translation-check-ignore
                'Environment variable "%s" is settable in %s but never offered by %s, so an operator copying the template cannot discover it. A commented-out assignment counts.',
                $name,
                $this->composeFilePath,
                $this->composeEnvExamplePath,
            ),
        );
    }

    /**
     * Everything the application reads has to reach a deployment, or be declared
     * as deliberately reaching none.
     */
    private function runApplicationScans(string $absPath, string $root): void
    {
        $appVars = self::unique(array_merge(
            self::parseAssignedEnvKeys($this->read($absPath) ?? ''),
            $this->collectEnvPlaceholders($root),
        ));

        $terraformDir = $root.'/'.$this->terraformDir;
        if (is_dir($terraformDir)) {
            $supplied = array_merge(
                $this->collectTerraformEnvKeys($terraformDir),
                $this->moduleProvidedEnvKeys,
                $this->ignoredAppEnvKeys,
            );

            foreach ($appVars as $name) {
                if (\in_array($name, $supplied, true)) {
                    continue;
                }

                $this->violations[] = new Violation(
                    sprintf( // @translation-check-ignore
                        'Environment variable "%s" is read by the application but assigned by nothing in %s/, so no Terraform deployment supplies it.',
                        $name,
                        $this->terraformDir,
                    ),
                    Severity::Error,
                    $root.'/'.$this->terraformReportPath,
                );
            }
        }

        $this->compare(
            $root.'/'.$this->composeFilePath,
            $appVars,
            $this->ignoredAppEnvKeys,
            fn (string $name): string => sprintf( // @translation-check-ignore
                'Environment variable "%s" is read by the application but named nowhere in %s, so a Compose deployment never supplies it.',
                $name,
                $this->composeFilePath,
            ),
        );

        // An exemption that outlives the variable it excuses is where the next
        // unwired variable hides. Only this list is a claim about this
        // application; a module legitimately injects names it never reads.
        foreach ($this->ignoredAppEnvKeys as $name) {
            if (\in_array($name, $appVars, true)) {
                continue;
            }

            $this->violations[] = new Violation(
                sprintf( // @translation-check-ignore
                    'Environment variable "%s" is exempted from the deployment scans but the application no longer reads it, so the exemption is stale.',
                    $name,
                ),
                Severity::Error,
                $absPath,
            );
        }
    }

    /**
     * @param list<string>             $names
     * @param list<string>             $ignored
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

        $this->compareAgainst($haystack, $expectedInAbsPath, $names, $ignored, $message);
    }

    /**
     * @param list<string>             $names
     * @param list<string>             $ignored
     * @param \Closure(string): string $message
     */
    private function compareAgainst(
        string $haystack,
        string $reportAbsPath,
        array $names,
        array $ignored,
        \Closure $message,
    ): void {
        foreach ($names as $name) {
            if (\in_array($name, $ignored, true)) {
                continue;
            }

            if (self::mentions($haystack, $name)) {
                continue;
            }

            $this->violations[] = new Violation($message($name), Severity::Error, $reportAbsPath);
        }
    }

    /**
     * The project root, when $absPath is $relPath resolved against it. Null when
     * the file being dispatched is another half of the check.
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

    /** @return list<string> */
    private function collectEnvPlaceholders(string $root): array
    {
        $names = [];
        foreach ($this->envReferenceDirs as $dir) {
            foreach (self::filesIn($root.'/'.$dir, ['php', 'yaml', 'yml', 'xml']) as $file) {
                foreach (self::parseEnvPlaceholders($this->read($file) ?? '') as $name) {
                    $names[] = $name;
                }
            }
        }

        return self::unique($names);
    }

    /**
     * Env keys Terraform actually assigns, rather than merely mentions. A
     * variable's `description` routinely names the environment variable it
     * feeds, so a whole-file text search is satisfied by prose while nothing
     * wires the value through.
     *
     * @return list<string>
     */
    private function collectTerraformEnvKeys(string $dir): array
    {
        $names = [];
        foreach (self::filesIn($dir, ['tf']) as $file) {
            $content = $this->read($file) ?? '';

            // `EXTRA_ENV_KEY = { value = ... }` in a map, and `key = "NAME"` in
            // an env block: the two shapes an App Platform spec is written in.
            preg_match_all('/([A-Z_][A-Z0-9_]*)\s*=\s*\{/', $content, $inMap);
            preg_match_all('/\bkey\s*=\s*"([A-Z_][A-Z0-9_]*)"/', $content, $inBlock);

            $names = array_merge($names, $inMap[1], $inBlock[1]);
        }

        return self::unique($names);
    }

    /**
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    private static function filesIn(string $dir, array $extensions): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo
                && $file->isFile()
                && \in_array($file->getExtension(), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
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
     * Assignments in a tfvars template, commented out or not.
     *
     * @return list<string>
     */
    private static function parseTerraformAssignments(string $content): array
    {
        preg_match_all('/^[ \t]*(?:#[ \t]*)?([a-z_][a-z0-9_]*)[ \t]*=/m', $content, $matches);

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
     * Live assignments only. A commented-out line documents an option in a
     * template, but in the dotenv that states what the application reads it is
     * a hint about a variable nothing resolves — `# TUNNEL_HOST=...` beside an
     * ngrok note is not a variable any deployment owes a value.
     *
     * @return list<string>
     */
    private static function parseAssignedEnvKeys(string $content): array
    {
        preg_match_all(
            '/^[ \t]*(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=/m',
            $content,
            $matches,
        );

        return self::unique($matches[1]);
    }

    /**
     * What an operator can actually set in a Compose file: an interpolation, in
     * any of its `${V}`, `${V:-default}` and `${V:?message}` forms, or a bare
     * `KEY:` passing the host's value through. A literal value is not settable,
     * so requiring it to be documented would be noise.
     *
     * @return list<string>
     */
    private static function parseComposeSettable(string $content): array
    {
        preg_match_all('/\$\{([A-Za-z_][A-Za-z0-9_]*)/', $content, $interpolated);
        preg_match_all('/^[ \t]+([A-Z_][A-Z0-9_]*):[ \t]*$/m', $content, $passthrough);

        return self::unique(array_merge($interpolated[1], $passthrough[1]));
    }

    /**
     * The name is the last colon-separated segment of a Symfony placeholder, so
     * it survives any number of processors and a named parameter fallback:
     * `%env(default:app.trusted_proxies_default:TRUSTED_PROXIES)%`.
     *
     * @return list<string>
     */
    private static function parseEnvPlaceholders(string $content): array
    {
        preg_match_all('/%env\(([^)]*)\)%/', $content, $matches);

        $names = [];
        foreach ($matches[1] as $expression) {
            $name = substr((string) strrchr(':'.$expression, ':'), 1);
            if (1 === preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
                $names[] = $name;
            }
        }

        return self::unique($names);
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
