# Gamache

> Chief Inspector — convention checker for Symfony projects.

Gamache packages a set of opinionated conventions for Symfony applications and enforces them through five tools you already use:

| Surface | What it provides | Docs |
|---|---|---|
| `gamache` CLI | 10 project-level checks (config files, templates, translations, …) | [docs/checks.md](docs/checks.md) |
| PHPStan | 23 rules for controllers, CQRS commands, forms, routes, entities, translations | [docs/phpstan-rules.md](docs/phpstan-rules.md) |
| PHP-CS-Fixer | 2 custom fixers for attribute formatting | [docs/php-cs-fixer.md](docs/php-cs-fixer.md) |
| Twig-CS-Fixer | 4 custom rules for templates | [docs/twig-cs-fixer.md](docs/twig-cs-fixer.md) |
| Rector | 1 rule that injects repositories instead of `getRepository()` calls | [docs/rector.md](docs/rector.md) |

Each surface is independent — adopt one, several, or all of them.

The conventions favor a specific architecture: single-action controllers secured by Voters, CQRS commands and handlers, DTO-backed forms, translation keys everywhere, and semantic Tailwind classes. If your project follows different conventions, pick only the rules that fit.

## Requirements

- PHP >= 8.5

## Installation

```bash
composer require --dev ubermuda/gamache
```

## The `gamache` CLI

The CLI runs project-level checks that static analysis can't cover: YAML config, Twig templates, XLF translation files, and cross-file consistency.

### Configuration

Create `gamache.php` at your project root. It must return a `GamacheConfig` with the checks you want:

```php
<?php

declare(strict_types=1);

use Gamache\Check\NoTodosCheck;
use Gamache\Check\ServicesYamlCheck;
use Gamache\Check\TranslationCheck;
use Gamache\Check\TranslationParityCheck;
use Gamache\Config\GamacheConfig;

return new GamacheConfig()->registerChecks([
    new NoTodosCheck(),
    new ServicesYamlCheck(),
    new TranslationParityCheck(),
    new TranslationCheck(
        threshold: 3,
        ignoredCallSites: ['LoggerInterface::info'],
    ),
]);
```

Checks are plain objects: instantiate them with the options you want. There is no auto-discovery. If `gamache.php` is missing (or returns anything other than a `GamacheConfig`), the CLI runs zero checks.

See [docs/checks.md](docs/checks.md) for every available check and its options.

### Running

Run from the project root (the CLI resolves paths against the current working directory):

```bash
vendor/bin/gamache
```

`run` is the default command. The only option is `--format`; `console` is the only format implemented today.

Sample output:

```
  ✔  NoTodosCheck
  ✗  TranslationParityCheck
       translations/messages.en.xlf:12
         Key 'app.welcome' is missing from locale 'fr'
  ⚠  TranslationCheck
       src/Service/Greeter.php:18
         Score 5  'Welcome to your dashboard'
  –  ServicesYamlCheck  (no matching files)

  1 passed · 1 failed · 1 advisory
```

### Severities and exit codes

Each violation carries a severity:

- **Error** — fails the check. Any failed check makes the CLI exit with code 1.
- **Warning** — marks the check *advisory* (`⚠`). Advisory checks never affect the exit code.

Checks whose target files don't exist are *skipped* (`–`) and don't affect the exit code either. Exit code 0 means no check failed.

## PHPStan rules

Include the extension in your `phpstan.neon`:

```neon
includes:
    - vendor/ubermuda/gamache/extension.neon
```

This registers all 23 rules at once. Five parameters control the configurable rules:

```neon
parameters:
    gamache:
        # Base class your controllers must extend (default: AbstractController)
        controllerBaseClass: 'App\Controller\AppController'

        # Extra call sites whose string argument must be a translation key
        translationCallSites:
            - class: 'App\Service\Mailer'
              method: 'sendWelcome'
              argumentIndex: 0

        # Attributes whose named arguments must be translation keys
        translationAttributeSites:
            - class: 'Symfony\Component\Validator\Constraints\NotBlank'
              argumentNames: ['message']

        # Repository classes exempt from the constructor-parameter naming
        # convention (default: Doctrine's base repository classes)
        repositoryNamingExcludedClasses:
            - 'App\Repository\LegacyRepository'

        # Controller-name/template-name consistency check (off when unset).
        # Group 1 of namespacePattern is the module path under templateDirectory.
        controllerTemplates:
            namespacePattern: '#^App\\Module\\(.+)\\Controller\\[^\\]+Controller$#'
            templateDirectory: 'templates/Module'
            renderMethods: [render, renderFormResponse]
```

See [docs/phpstan-rules.md](docs/phpstan-rules.md) for every rule, its error identifier, and examples. Rules without parameters cannot be configured individually — use PHPStan's `ignoreErrors` with the rule's error identifier to opt out of one.

## PHP-CS-Fixer fixers

Register the custom fixers in `.php-cs-fixer.dist.php`:

```php
use Gamache\PhpCsFixer\BlankLineBetweenAttributedParametersFixer;
use Gamache\PhpCsFixer\MultilineAttributeFixer;

return (new PhpCsFixer\Config())
    ->registerCustomFixers([
        new BlankLineBetweenAttributedParametersFixer(),
        new MultilineAttributeFixer(),
    ])
    ->setRules([
        'Gamache/blank_line_between_attributed_parameters' => true,
        'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 1],
    ]);
```

See [docs/php-cs-fixer.md](docs/php-cs-fixer.md).

## Twig-CS-Fixer rules

Register the rules in `.twig-cs-fixer.php`:

```php
use Gamache\TwigCsFixer\CsrfTokenValueRule;
use Gamache\TwigCsFixer\IncludeOnlyRule;
use Gamache\TwigCsFixer\InlineSvgRule;
use Gamache\TwigCsFixer\TranslationKeyRule;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Ruleset\Ruleset;

$ruleset = new Ruleset();
$ruleset->addRule(new CsrfTokenValueRule());
$ruleset->addRule(new IncludeOnlyRule());
$ruleset->addRule(new InlineSvgRule());
$ruleset->addRule(new TranslationKeyRule());

return (new Config())->setRuleset($ruleset);
```

These rules report violations; they don't rewrite your templates. See [docs/twig-cs-fixer.md](docs/twig-cs-fixer.md).

## Rector rule

Register the rule in `rector.php`:

```php
use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        InjectRepositoryInsteadOfGetRepositoryRector::class,
    ]);
```

See [docs/rector.md](docs/rector.md).

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## License

MIT
