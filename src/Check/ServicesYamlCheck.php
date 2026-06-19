<?php

declare(strict_types=1);

namespace Gamache\Check;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ServicesYamlCheck extends AbstractCheck
{
    public function getName(): string
    {
        return 'ServicesYamlCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['config/services.yaml'];
    }

    public function run(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        try {
            $parsed = Yaml::parse($content);
        } catch (ParseException) {
            return;
        }

        if (!is_array($parsed)) {
            return;
        }

        $services = $parsed['services'] ?? null;
        if (!is_array($services)) {
            return;
        }

        if (array_key_exists('_instanceof', $services)) {
            $this->violations[] = new Violation(
                '_instanceof blocks are not allowed; use #[AutoconfigureTag(\'app.tag\')] on the interface instead', // @translation-check-ignore
                Severity::Error,
                $absPath,
            );
        }

        foreach ($services as $id => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if (!array_key_exists('arguments', $definition)) {
                continue;
            }

            // Third-party services (e.g. a bundle's class or a string-keyed id)
            // cannot be configured with #[Autowire] attributes because you do not
            // own their constructor, so an explicit arguments: block is the only
            // mechanism available — exempt them. The ban only targets App\ classes,
            // whose constructors you can annotate instead.
            $class = $definition['class'] ?? $id;
            if (!is_string($class) || !str_starts_with($class, 'App\\')) {
                continue;
            }

            $this->violations[] = new Violation(
                'Explicit arguments: blocks are not allowed for App\\ services; use #[Autowire(env: \'...\')] on the constructor parameter instead', // @translation-check-ignore
                Severity::Error,
                $absPath,
            );
        }
    }
}
