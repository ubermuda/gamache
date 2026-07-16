<?php

declare(strict_types=1);

namespace Gamache\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Template paths under the module template root must be referenced through
 * their Twig namespace: `@Event/show.html.twig`, not `Module/Event/show.html.twig`.
 *
 * Fires on string literals passed as the first argument of the configured
 * render methods (`render()`, `renderView()`, `htmlTemplate()`, `textTemplate()`
 * by default). Dynamically-assembled paths cannot be checked statically and
 * remain convention-only.
 *
 * Off by default: enable by setting `gamache.templateNamespaces.forbiddenPathPrefix`
 * (e.g. 'Module/') in the consumer's phpstan.neon.
 *
 * @implements Rule<MethodCall>
 */
final readonly class ModuleTemplateNamespaceRule implements Rule
{
    /** @param list<string> $renderMethods */
    public function __construct(
        private string $forbiddenPathPrefix,
        private array $renderMethods,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return list<RuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        \assert($node instanceof MethodCall);

        if ('' === $this->forbiddenPathPrefix) {
            return [];
        }

        if (!$node->name instanceof Identifier || !\in_array($node->name->name, $this->renderMethods, true)) {
            return [];
        }

        $template = $node->getArgs()[0]->value ?? null;
        if (!$template instanceof String_) {
            return [];
        }

        $pattern = '#^'.preg_quote($this->forbiddenPathPrefix, '#').'([A-Z][A-Za-z0-9]*)/(.+)$#';
        if (1 !== preg_match($pattern, $template->value, $matches)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Template "%s" must be referenced through its Twig namespace: "@%s/%s".',
                $template->value,
                $matches[1],
                $matches[2],
            ))
            ->identifier('template.moduleNamespace')
            ->line($template->getLine())
            ->build(),
        ];
    }
}
