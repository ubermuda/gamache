# Tool presets (aggregates) for gamache

## Goal

Let consumers enable gamache's conventions for each external tool through a
single gamache-owned aggregate, instead of registering each fixer/rule by hand.
The defining requirement: **when a consumer upgrades gamache, new rules flow in
automatically** — their config references the aggregate, never a copied list, so
a `composer update` that adds rule N+1 needs no edit on their side.

This covers the three tools that currently require manual per-item wiring:
PHP-CS-Fixer, Rector, and Twig-CS-Fixer. PHPStan already has this via the
`extension.neon` include and is out of scope.

## Principle: one aggregate per tool, single source of truth

Each tool gets one gamache-owned aggregate. Where the tool has a native
aggregate mechanism, gamache uses it; only PHP-CS-Fixer needs a custom helper.

| Tool          | Aggregate                                  | Mechanism            |
|---------------|--------------------------------------------|----------------------|
| PHPStan       | `extension.neon` include (already shipped) | native — out of scope|
| Rector        | `GamacheSetList::CONVENTIONS`              | native set list      |
| Twig-CS-Fixer | `GamacheStandard`                          | native `StandardInterface` |
| PHP-CS-Fixer  | `Fixers` collection + `Fixers::rules()`    | custom helper        |

For PHP-CS-Fixer the fixer collection and the rule map derive from one internal
list so they cannot drift (a rule naming a fixer that isn't registered is an
error).

## Set contents

Each aggregate bundles gamache's own custom code **plus** the curated built-in
rules that enforce the conventions discussed (attribute alphabetical ordering;
named-argument ordering).

### PHP-CS-Fixer — `Gamache\PhpCsFixer\Fixers`

- `Fixers implements IteratorAggregate` — yields every custom fixer instance:
  - `BlankLineBetweenAttributedParametersFixer`
  - `MultilineAttributeFixer`
- `Fixers::rules(): array` — the full recommended rule map:
  - `'Gamache/blank_line_between_attributed_parameters' => true`
  - `'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 1]`
  - `'ordered_attributes' => true` (curated built-in: alphabetical attribute order)

`registerCustomFixers(iterable)` accepts the collection (verified against
ConfigInterface). The custom-fixer instances and their rule-map entries are
generated from one private list inside `Fixers`; `ordered_attributes` (a
built-in, no gamache fixer) is appended in `rules()` only.

Consumer usage:

```php
use Gamache\PhpCsFixer\Fixers;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new Fixers())
    ->setRules([
        '@Symfony' => true,
        ...Fixers::rules(),   // gamache conventions; new ones arrive on upgrade
    ]);
```

### Rector — `Gamache\Rector\GamacheSetList`

A `GamacheSetList` class with one constant `CONVENTIONS` pointing to a set config
file. **One set** (decision: not split). The set lists:

- `Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector` (existing custom rule)
- `Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector` (built-in)
- `Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector` (built-in)

```php
// src/Rector/config/conventions.php (the set file)
return static function (RectorConfig $config): void {
    $config->rule(InjectRepositoryInsteadOfGetRepositoryRector::class);
    $config->rule(SortCallLikeNamedArgsRector::class);
    $config->rule(SortAttributeNamedArgsRector::class);
};
```

```php
// GamacheSetList
final class GamacheSetList
{
    public const CONVENTIONS = __DIR__ . '/config/conventions.php';
}
```

Consumer usage:

```php
use Gamache\Rector\GamacheSetList;

return RectorConfig::configure()
    ->withSets([GamacheSetList::CONVENTIONS]);   // grows on upgrade
```

### Twig-CS-Fixer — `Gamache\TwigCsFixer\GamacheStandard`

`GamacheStandard implements TwigCsFixer\Standard\StandardInterface`, `getRules()`
returns instances of all four existing rules:

- `CsrfTokenValueRule`, `IncludeOnlyRule`, `InlineSvgRule`, `TranslationKeyRule`

Consumer usage:

```php
use Gamache\TwigCsFixer\GamacheStandard;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Ruleset\Ruleset;

$ruleset = new Ruleset();
$ruleset->addStandard(new GamacheStandard());   // grows on upgrade

return (new Config())->setRuleset($ruleset);
```

## Verified facts

- `ConfigInterface::registerCustomFixers(iterable $fixers)` — accepts a
  Traversable, so an `IteratorAggregate` collection works (matches the
  kubawerlos `Fixers` convention).
- Rector built-in FQCNs confirmed present in `rector/rector ^2.0`:
  `Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector`,
  `Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector`.
- Twig-CS-Fixer `StandardInterface::getRules()` + `Ruleset::addStandard()` exist
  in `vincentlanglet/twig-cs-fixer ^3.0` and are the idiom used by its own
  `Symfony`/`Twig` standards.

## Accepted tradeoff

Auto-upgrade is intentionally double-edged: a new gamache release can start
applying a new fixer/rector rule on the consumer's next `composer update` +
format/refactor run. Mitigation is disciplined semver and release notes on the
gamache side. This is inherent to the requirement, not a defect.

The single Rector set bundles the invasive `InjectRepository…` refactor with the
pure-style sorters; adopting the sorters means adopting the refactor too. Chosen
deliberately for simplicity; can split into a separate `REFACTORS` set later if
it causes friction.

## Tests

- `Fixers` smoke test: collection is non-empty; every custom-fixer rule name in
  `rules()` resolves to a registered fixer (catches drift); `ordered_attributes`
  present.
- `GamacheSetList::CONVENTIONS` resolves to a readable set file that loads
  without error and registers the three expected rules.
- `GamacheStandard::getRules()` returns the four expected rule instances.

## Docs

Rewrite the aggregate usage in `README.md` and in `docs/php-cs-fixer.md`,
`docs/rector.md`, `docs/twig-cs-fixer.md`. Each must state explicitly that
referencing the aggregate (not listing rules) is what makes upgrades automatic.

## Out of scope

- PHPStan (already aggregated via `extension.neon`).
- The new php-cs-fixer `registerCustomRuleSet` / `@Gamache/conventions` API
  (rejected: requires raising the `^3.0` floor and relies on an `@internal` API).
- Splitting the Rector set.
