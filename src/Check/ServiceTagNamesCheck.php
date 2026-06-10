<?php

declare(strict_types=1);

namespace Gamache\Check;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class ServiceTagNamesCheck extends AbstractCheck
{
    public function getName(): string
    {
        return 'ServiceTagNamesCheck';
    }

    public function getTargetPatterns(): array
    {
        return ['src/**/*.php', 'config/services.yaml'];
    }

    public function run(string $absPath): void
    {
        if (str_ends_with($absPath, '.php')) {
            $this->scanPhpFile($absPath);
        } else {
            $this->scanServicesYaml($absPath);
        }
    }

    private function scanPhpFile(string $absPath): void
    {
        $content = @file_get_contents($absPath);
        if (false === $content) {
            return;
        }

        $parser = new ParserFactory()->createForHostVersion();

        try {
            $stmts = $parser->parse($content);
        } catch (\PhpParser\Error) {
            return;
        }

        if (null === $stmts) {
            return;
        }

        $nodeFinder = new NodeFinder();

        /** @var Node\Attribute[] $attributes */
        $attributes = $nodeFinder->findInstanceOf($stmts, Node\Attribute::class);

        $targetShortNames = ['AutoconfigureTag', 'AutowireIterator', 'AutowireTagged'];

        foreach ($attributes as $attribute) {
            $fullName = $attribute->name->toString();
            $pos = strrpos($fullName, '\\');
            $shortName = false === $pos ? $fullName : substr($fullName, $pos + 1);

            if (!in_array($shortName, $targetShortNames, true)) {
                continue;
            }

            if ([] === $attribute->args) {
                continue;
            }

            $firstValue = $attribute->args[0]->value;
            if (!$firstValue instanceof Node\Scalar\String_) {
                continue;
            }

            $tagName = $firstValue->value;
            if (!str_starts_with($tagName, 'app.')) {
                $this->violations[] = new Violation(
                    sprintf("Service tag '%s' must use the 'app.' prefix", $tagName), // @translation-check-ignore
                    Severity::Error,
                    $absPath,
                    $attribute->getLine(),
                );
            }
        }
    }

    private function scanServicesYaml(string $absPath): void
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

        foreach ($services as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $tags = $definition['tags'] ?? null;
            if (!is_array($tags)) {
                continue;
            }

            foreach ($tags as $tag) {
                $tagName = null;

                if (is_string($tag)) {
                    $tagName = $tag;
                } elseif (is_array($tag) && isset($tag['name']) && is_string($tag['name'])) {
                    $tagName = $tag['name'];
                }

                if (null === $tagName) {
                    continue;
                }

                if (!str_starts_with($tagName, 'app.')) {
                    $this->violations[] = new Violation(
                        sprintf("Service tag '%s' must use the 'app.' prefix", $tagName), // @translation-check-ignore
                        Severity::Error,
                        $absPath,
                    );
                }
            }
        }
    }
}
