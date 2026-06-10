<?php

declare(strict_types=1);

namespace Gamache\Check;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final class FormTypeTranslationKeysCheck extends AbstractCheck
{
    /**
     * @param string       $filePattern          Glob pattern used to locate FormType files.
     *                                           Override when your project does not use the
     *                                           `src/Module/` structure.
     * @param ?string      $moduleExtractPattern A regex with one capture group that extracts
     *                                           the module name from the absolute file path.
     *                                           When null, the default `/Module/([^/]+)/`
     *                                           pattern is used. Files that do not match are
     *                                           silently skipped.
     * @param list<string> $userFacingOptions    Form option names whose string values are
     *                                           expected to be translation keys. Extend this
     *                                           list when your project uses custom options
     *                                           that hold translation keys.
     */
    public function __construct(
        private readonly string $filePattern = 'src/Module/**/*FormType.php',
        private readonly ?string $moduleExtractPattern = null,
        private readonly array $userFacingOptions = ['label', 'help', 'placeholder', 'invalid_message', 'choice_label'],
    ) {
    }

    public function getName(): string
    {
        return 'FormTypeTranslationKeysCheck';
    }

    public function getTargetPatterns(): array
    {
        return [$this->filePattern];
    }

    public function run(string $absPath): void
    {
        $className = basename($absPath, '.php');

        // Derive module name from path using the configured or default pattern.
        $pattern = $this->moduleExtractPattern ?? '#/Module/([^/]+)/#';
        if (!preg_match($pattern, $absPath, $m)) {
            return;
        }
        $moduleName = strtolower($m[1]);

        // Derive Symfony block prefix via inlined algorithm
        $blockPrefix = self::fqcnToBlockPrefix($className);
        if (null === $blockPrefix) {
            return;
        }

        $requiredPrefix = sprintf('%s.form.%s.', $moduleName, $blockPrefix);

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

        /** @var MethodCall[] $calls */
        $calls = $nodeFinder->find($stmts, static fn (\PhpParser\Node $node): bool => $node instanceof MethodCall
            && $node->name instanceof \PhpParser\Node\Identifier
            && 'add' === $node->name->name);

        foreach ($calls as $call) {
            // The options array is the 3rd argument (index 2).
            if (!isset($call->args[2])) {
                continue;
            }

            $optionsArg = $call->args[2];
            if (!$optionsArg instanceof \PhpParser\Node\Arg) {
                continue;
            }

            if (!$optionsArg->value instanceof Array_) {
                continue;
            }

            foreach ($optionsArg->value->items as $item) {
                if (!$item instanceof ArrayItem) {
                    continue;
                }

                if (!$item->key instanceof String_) {
                    continue;
                }

                if (!in_array($item->key->value, $this->userFacingOptions, true)) {
                    continue;
                }

                if (!$item->value instanceof String_) {
                    // Skip non-static values (variables, concatenations, etc.)
                    continue;
                }

                $key = $item->value->value;
                if (!str_starts_with($key, $requiredPrefix)) {
                    $this->violations[] = new Violation(
                        sprintf("key must start with '%s'", $requiredPrefix),
                        Severity::Error,
                        $absPath,
                        $item->getStartLine(),
                    );
                }
            }
        }
    }

    private static function fqcnToBlockPrefix(string $fqcn): ?string
    {
        if (preg_match('~([^\\\\]+?)(type)?$~i', $fqcn, $matches)) {
            return strtolower(preg_replace(
                ['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'],
                ['\\1_\\2', '\\1_\\2'],
                $matches[1],
            ));
        }

        return null;
    }
}
