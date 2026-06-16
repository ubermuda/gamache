# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Per-tool convention presets (aggregates).** Each external tool now has a
  single gamache-owned aggregate you reference from your config, instead of
  registering each fixer/rule by hand. Referencing the aggregate means new
  gamache rules apply automatically when you `composer update` — no config edit
  required.
  - PHP-CS-Fixer: `Gamache\PhpCsFixer\Fixers` — a collection of the custom
    fixers plus `Fixers::rules()`, which now also enables the built-in
    `ordered_attributes` rule (alphabetical attribute ordering).
  - Rector: `Gamache\Rector\GamacheSetList::CONVENTIONS` — bundles
    `InjectRepositoryInsteadOfGetRepositoryRector` plus the built-in
    `SortCallLikeNamedArgsRector` and `SortAttributeNamedArgsRector`, which
    reorder named arguments to match parameter declaration order.
  - Twig-CS-Fixer: `Gamache\TwigCsFixer\GamacheStandard` — bundles all four
    gamache Twig rules.

### Upgrade guide

Switch each tool's config to the aggregate. This is the change that opts you in
to automatic rule updates on future `composer update`.

#### PHP-CS-Fixer (`.php-cs-fixer.dist.php`)

```diff
-use Gamache\PhpCsFixer\BlankLineBetweenAttributedParametersFixer;
-use Gamache\PhpCsFixer\MultilineAttributeFixer;
+use Gamache\PhpCsFixer\Fixers;

 return (new PhpCsFixer\Config())
-    ->registerCustomFixers([
-        new BlankLineBetweenAttributedParametersFixer(),
-        new MultilineAttributeFixer(),
-    ])
-    ->setRules([
-        'Gamache/blank_line_between_attributed_parameters' => true,
-        'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 1],
-    ]);
+    ->registerCustomFixers(new Fixers())
+    ->setRules([
+        '@Symfony' => true,
+        ...Fixers::rules(),
+    ]);
```

Spread `...Fixers::rules()` after your own base ruleset (e.g. `@Symfony`); list
any per-rule overrides after it. `Fixers::rules()` now enables
`ordered_attributes`, so running the fixer will reorder multiple attributes on a
declaration alphabetically.

#### Rector (`rector.php`)

```diff
-use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
+use Gamache\Rector\GamacheSetList;
 use Rector\Config\RectorConfig;

 return RectorConfig::configure()
-    ->withRules([
-        InjectRepositoryInsteadOfGetRepositoryRector::class,
-    ]);
+    ->withSets([GamacheSetList::CONVENTIONS]);
```

The set adds two named-argument sorters. The first `rector process` after
upgrading will reorder named arguments in calls and attributes to match
declaration order, and `InjectRepositoryInsteadOfGetRepositoryRector` will
rewrite constructors to inject repositories. Run with `--dry-run` first to
review the diff.

#### Twig-CS-Fixer (`.twig-cs-fixer.php`)

```diff
-use Gamache\TwigCsFixer\CsrfTokenValueRule;
-use Gamache\TwigCsFixer\IncludeOnlyRule;
-use Gamache\TwigCsFixer\InlineSvgRule;
-use Gamache\TwigCsFixer\TranslationKeyRule;
+use Gamache\TwigCsFixer\GamacheStandard;
 use TwigCsFixer\Config\Config;
 use TwigCsFixer\Ruleset\Ruleset;

 $ruleset = new Ruleset();
-$ruleset->addRule(new CsrfTokenValueRule());
-$ruleset->addRule(new IncludeOnlyRule());
-$ruleset->addRule(new InlineSvgRule());
-$ruleset->addRule(new TranslationKeyRule());
+$ruleset->addStandard(new GamacheStandard());

 return (new Config())->setRuleset($ruleset);
```

#### Note on automatic updates

Because the aggregates pull in new rules automatically, a future gamache release
can introduce a fixer or Rector rule that changes your code on the next
`composer update` + format/refactor run. Pin gamache to an exact version, or
review release notes and run formatters/Rector in `--dry-run` mode after
upgrading, if you want to gate that.

You can still register individual fixers/rules by hand if you prefer to opt out
of automatic updates; see the per-tool docs in `docs/`.
