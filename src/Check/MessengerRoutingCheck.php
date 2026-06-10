<?php

declare(strict_types=1);

namespace Gamache\Check;

use Symfony\Component\Yaml\Yaml;

final class MessengerRoutingCheck extends AbstractCheck
{
    /** @var list<string> */
    private array $declaredFqcns = [];
    private string $yamlAbsPath = '';

    public function getName(): string
    {
        return 'MessengerRoutingCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['config/packages/messenger.yaml'];
    }

    public function run(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $this->yamlAbsPath = $absPath;

        $parsed = Yaml::parse($content);

        $routing = $parsed['framework']['messenger']['routing'] ?? null;
        if (!\is_array($routing)) {
            return;
        }

        foreach (array_keys($routing) as $fqcn) {
            if (!\is_string($fqcn) || !str_starts_with($fqcn, 'App\\')) {
                continue;
            }

            $this->declaredFqcns[] = $fqcn;
        }
    }

    #[\Override]
    public function getResult(): CheckResult
    {
        $violations = [];
        $projectRoot = substr($this->yamlAbsPath, 0, -\strlen('/config/packages/messenger.yaml'));

        foreach ($this->declaredFqcns as $fqcn) {
            $relative = str_replace('\\', '/', substr($fqcn, \strlen('App\\')));

            if (file_exists($projectRoot.'/src/'.$relative.'.php')) {
                continue;
            }

            $violations[] = new Violation(
                sprintf("Class '%s' not found (expected src/%s.php)", $fqcn, $relative),
                Severity::Error,
                $this->yamlAbsPath,
            );
        }

        return new CheckResult($this->getName(), $violations);
    }
}
