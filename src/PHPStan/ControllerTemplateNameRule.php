<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Controller class names must map to a template filename: a controller that
 * renders a page template must have a template whose name matches the class
 * name (`CreateProjectController` → `create_project.html.twig`) somewhere
 * under the module's template directory.
 *
 * The controller's module is captured by the configured namespace pattern;
 * the template must live under `<templateDirectory>/<module>`. Matching is
 * lenient: case, underscores, and directory separators are ignored, and the
 * filename alone may carry the name — `registration/check_email.html.twig`
 * satisfies `RegistrationCheckEmailController`, `security/login.html.twig`
 * satisfies `LoginController`. Controllers that render nothing (redirects,
 * JSON) or render only partials (`_*.html.twig`) are skipped.
 *
 * @implements Rule<Class_>
 */
final readonly class ControllerTemplateNameRule implements Rule
{
    private const string TEMPLATE_EXTENSION = '.html.twig';
    private const string CONTROLLER_SUFFIX = 'Controller';

    /** @param list<string> $renderMethods */
    public function __construct(
        private string $namespacePattern,
        private string $templateDirectory,
        private array $renderMethods,
        private string $currentWorkingDirectory,
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof Class_);

        if ('' === $this->namespacePattern || '' === $this->templateDirectory) {
            return [];
        }

        if (null === $node->name || null === $node->namespacedName || $node->isAbstract()) {
            return [];
        }

        $fqcn = $node->namespacedName->toString();
        if (1 !== preg_match($this->namespacePattern, $fqcn, $matches) || !isset($matches[1])) {
            return [];
        }

        $className = $node->name->name;
        if (!str_ends_with($className, self::CONTROLLER_SUFFIX)) {
            return [];
        }

        if (!$this->rendersPageTemplate($node)) {
            return [];
        }

        $stem = substr($className, 0, -\strlen(self::CONTROLLER_SUFFIX));
        $moduleDirectory = $this->templateDirectory.'/'.str_replace('\\', '/', $matches[1]);

        if ($this->moduleHasMatchingTemplate($moduleDirectory, $this->normalize($stem))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Controller %s renders a template but none under %s matches its name (expected "%s%s"; matching ignores case, underscores, and directories).',
                $className,
                $moduleDirectory,
                $this->snakeCase($stem),
                self::TEMPLATE_EXTENSION,
            ))
            ->identifier('controller.templateName')
            ->line($node->getLine())
            ->build(),
        ];
    }

    /**
     * A controller "renders a page template" when it calls one of the
     * configured render methods with anything other than a partial
     * (`_*.html.twig`) as the template argument.
     */
    private function rendersPageTemplate(Class_ $class): bool
    {
        $calls = (new NodeFinder())->findInstanceOf($class->stmts, MethodCall::class);
        foreach ($calls as $call) {
            if (!$call->name instanceof Identifier || !\in_array($call->name->name, $this->renderMethods, true)) {
                continue;
            }

            $template = $call->getArgs()[0]->value ?? null;
            if ($template instanceof String_ && $this->isPartial($template->value)) {
                continue;
            }

            // Literal page template, or a dynamic argument we can't inspect:
            // either way the controller is expected to own a template.
            return true;
        }

        return false;
    }

    private function isPartial(string $template): bool
    {
        $basename = false === ($pos = strrpos($template, '/')) ? $template : substr($template, $pos + 1);

        return str_starts_with($basename, '_');
    }

    private function moduleHasMatchingTemplate(string $moduleDirectory, string $normalizedStem): bool
    {
        $root = str_starts_with($moduleDirectory, '/')
            ? $moduleDirectory
            : $this->currentWorkingDirectory.'/'.$moduleDirectory;

        if (!is_dir($root)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if (!$file->isFile() || !str_ends_with($file->getFilename(), self::TEMPLATE_EXTENSION)) {
                continue;
            }
            if (str_starts_with($file->getFilename(), '_')) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1, -\strlen(self::TEMPLATE_EXTENSION));
            if ($this->normalize($relative) === $normalizedStem) {
                return true;
            }

            // Directory-grouped flows: `security/login.html.twig` satisfies
            // LoginController — the filename alone may carry the name.
            $filename = substr($file->getFilename(), 0, -\strlen(self::TEMPLATE_EXTENSION));
            if ($this->normalize($filename) === $normalizedStem) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $name): string
    {
        return strtolower(str_replace(['_', '/'], '', $name));
    }

    private function snakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));
    }
}
