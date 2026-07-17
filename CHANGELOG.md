# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **`ServicesYamlCheck`: exempt third-party services from the `arguments:` ban.**
  The ban on explicit `arguments:` blocks now applies only to services whose class
  is in the `App\` namespace (the definition key, or its `class:` when set). You own
  those constructors and can configure them with `#[Autowire]` attributes. Third-party
  services — bundle classes or string-keyed ids — are exempt, since you cannot
  annotate a constructor you do not own, making an explicit `arguments:` block the
  only available mechanism (e.g. configuring a bundle middleware via an env var).

### Added

- **`ApiRouteConsistencyRule` + `ApiControllerInputBindingRule`: enforce JSON API conventions.**
  `ApiRouteConsistencyRule` (`route.apiConsistency`) requires a route's `/api/` path,
  `api_` name, and `\Controller\Api\` namespace to agree — catching misplaced
  controllers and mis-prefixed names. `ApiControllerInputBindingRule`
  (`controller.apiInputBinding`) forbids Symfony forms and raw request-body parsing in
  `\Controller\Api\` controllers, which must bind input via `#[MapRequestPayload]`.
- **`PageTitleBrandNameCheck`: keep the brand out of page-title translation values.**
  Flags any `*.page.title` `<target>` that contains the brand (read from the same
  file's `app.name` target, per locale) or a ` — ` separator — the brand belongs in
  the template, composed as `{{ 'x.page.title'|trans }} — {{ 'app.name'|trans }}`.
- **`TranslationKeyRule`: `{% trans %}` blocks, `<style>`/`<script>` skipping, and
  an `excludedPaths` option.**
  - Text inside a `{% trans %}…{% endtrans %}` block (including the
    `{% trans with {…} %}` variant) is now validated as a translation key rather
    than flagged as raw text — so `{% trans %}some.key{% endtrans %}` passes while
    `{% trans %}Welcome back{% endtrans %}` is flagged, mirroring how
    `'Welcome back'|trans` is already handled.
  - The textual content of `<style>` and `<script>` elements is skipped, so inline
    CSS/JS is no longer reported as raw text.
  - `new TranslationKeyRule(excludedPaths: ['*/admin/*'])` skips files whose path
    matches any `fnmatch()` pattern — for exempting areas not yet translated.
    `GamacheStandard` keeps the no-argument default (no exclusions).

- **Per-tool convention presets (aggregates).** Each external tool now has a
  single gamache-owned aggregate you reference from your config, instead of
  registering each fixer/rule by hand. Referencing the aggregate means new
  gamache rules apply automatically when you `composer update` — no config edit
  required.
  - PHP-CS-Fixer: `Gamache\PhpCsFixer\Fixers` — a collection of the custom
    fixers plus `Fixers::rules()`. Beyond the two custom fixers, the rule map
    enables `multiline_promoted_properties`, `php_unit_method_casing`
    (snake_case), `ordered_attributes` (alphabetical attribute ordering), and
    sets `Gamache/multiline_attribute` to `minimum_arguments: 3`.
  - Rector: `Gamache\Rector\GamacheSetList::CONVENTIONS` — bundles
    `InjectRepositoryInsteadOfGetRepositoryRector`, the built-in
    `SortCallLikeNamedArgsRector` and `SortAttributeNamedArgsRector` (reorder
    named arguments to match parameter declaration order), and `PropertyHookRector`
    (PHP 8.4 property hooks).
  - Twig-CS-Fixer: `Gamache\TwigCsFixer\GamacheStandard` — bundles all four
    gamache Twig rules.

  These defaults match the conventions used across consuming projects, so those
  projects can drop the matching inline rules from their own configs.

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
+    ->registerCustomFixers(new Fixers())
     ->setRules([
         '@Symfony' => true,
-        'multiline_promoted_properties' => true,
-        'Gamache/blank_line_between_attributed_parameters' => true,
-        'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 3],
-        'php_unit_method_casing' => ['case' => 'snake_case'],
+        ...Fixers::rules(),
     ]);
```

Spread `...Fixers::rules()` after your own base ruleset (e.g. `@Symfony`); list
any per-rule overrides after it. `Fixers::rules()` now enables
`ordered_attributes`, so running the fixer will reorder multiple attributes on a
declaration alphabetically.

#### Rector (`rector.php`)

Keep your project-level `withPhpSets()`, `withPreparedSets()`, etc.; only the
gamache rules move into the set. `PropertyHookRector` is now part of the set, so
drop it from `withRules()` if you had it there.

```diff
-use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
+use Gamache\Rector\GamacheSetList;
 use Rector\Config\RectorConfig;
-use Rector\Php84\Rector\Class_\PropertyHookRector;

 return RectorConfig::configure()
+    ->withSets([GamacheSetList::CONVENTIONS])
     ->withPhpSets(php85: true)
-    ->withRules([
-        InjectRepositoryInsteadOfGetRepositoryRector::class,
-        PropertyHookRector::class,
-    ]);
+;
```

The set adds two named-argument sorters and `PropertyHookRector`. The first
`rector process` after upgrading will reorder named arguments in calls and
attributes to match declaration order, and
`InjectRepositoryInsteadOfGetRepositoryRector` will
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
