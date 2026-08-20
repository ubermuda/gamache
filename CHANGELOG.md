# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`DeploymentConfigParityCheck`: deployment variables must reach the file an operator copies.**
  Compares two pairs of files, both configurable and both skipped when either half is
  absent: every `variable "x" {` in `terraform/variables.tf` must be named somewhere in
  `terraform/terraform.tfvars.example`, and every assignment in
  `docker/compose/prod.env.example` must be referenced somewhere in
  `docker/compose/prod.yaml`. A commented-out mention counts on both sides — a template
  is documentation, and `# export_storage_key = "..."` is the right way to document an
  optional variable. Both pairs are checked in both directions, so a template entry no
  variable backs — a knob an operator sets and the deployment ignores — is reported too.

  The check also starts one step earlier, at what the application reads: the committed
  dotenv plus every `%env(...)%` placeholder under the configured reference directories,
  since neither is complete alone. Each such variable must be named somewhere in
  `terraform/` and somewhere in the Compose file, which is what catches a variable wired
  into no deployment at all — the failure the file-pair scans cannot see, because every
  file that would have mentioned it is consistent without it. Names an external Terraform
  module injects go in `moduleProvidedEnvKeys`, and names deliberately reaching no
  deployment in `ignoredAppEnvKeys`, whose entries are reported once the application stops
  reading them — an exemption that outlives its variable is where the next unwired variable
  hides. Dot-directories are skipped when scanning, so generated copies of other people's
  code — `terraform/.terraform/` above all — are not mistaken for a project's own wiring.

  The failure it catches is that nothing else catches it. `terraform validate` passes
  whether or not the tfvars example mentions a variable, because that file is comment-only
  and no tool reads it. Compose starts fine whether or not `prod.yaml` interpolates a key,
  because an env file supplies interpolation values without injecting them into containers —
  so an operator can set a documented variable, watch it be ignored, and have no way to
  diagnose why. Both leave the option real but undiscoverable.

  It came out of a downstream branch that wired a variable end-to-end and missed the
  template twice in a row: once in Terraform, once in Compose. Two green gates, two
  options nobody could find. Pointed at that project's `main` afterwards, the check
  immediately found a third: `export_storage` declared and consumed, with only its
  `export_storage_*` siblings documented.

- **`CommentBudgetCheck`: a `severity` option, so the budget can be made binding.**
  Defaults to `Severity::Warning`, which is the existing behaviour and leaves the
  exit code untouched — nothing changes for a project that does not pass the new
  argument. Pass `Severity::Error` and an unsuppressed over-budget block fails the
  run.

  The reason to want that: a check that cannot fail is a check whose green result
  carries no information, and advisory output is easy to stop reading. In one
  downstream session, three over-budget blocks were introduced and shipped through
  three consecutive green gates before anyone noticed — on a branch that had just
  merged a comment-budget sweep. All three turned out to be real and were fixed by
  shortening rather than suppressing.

  What makes enforcement tenable is `@comment-budget-ignore`: a line count cannot
  tell a good six-line comment from a bad one, but the marker supplies exactly that
  judgment. A project that marks its deliberate long blocks can then treat every
  remaining one as an oversight.

### Changed

- **`ServicesYamlCheck`: exempt third-party services from the `arguments:` ban.**
  The ban on explicit `arguments:` blocks now applies only to services whose class
  is in the `App\` namespace (the definition key, or its `class:` when set). You own
  those constructors and can configure them with `#[Autowire]` attributes. Third-party
  services — bundle classes or string-keyed ids — are exempt, since you cannot
  annotate a constructor you do not own, making an explicit `arguments:` block the
  only available mechanism (e.g. configuring a bundle middleware via an env var).

### Fixed

- **`CommentBudgetCheck`: Symfony Flex section markers no longer fuse two comments
  into one run.** `###> pkg ###` / `###< pkg ###` are `#` lines, so a `.env` with
  two in-budget comments on either side of a marker was reported as one long
  block. The markers now break a run, as code does. Measured on one downstream
  app, this was every remaining finding in `.env` and `compose.yaml`.

### Added

- **`CommentBudgetCheck`: flag comment blocks that have outgrown their usefulness.**
  Reports runs of consecutive comment lines longer than `maxLines` (default 5) as
  warnings, so the run still exits 0. Comment syntax follows the file extension —
  PHP is tokenised, `//` covers JS/TS/CSS, `{# #}` covers Twig, and anything
  unrecognised is read as `#`-commented, which handles YAML, dotenv, justfiles and
  shell. Docblocks and JSDoc are exempt, since annotations rather than prose drive
  their length; `@comment-budget-ignore` suppresses a false positive. See
  [docs/checks.md](docs/checks.md#commentbudgetcheck).
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
