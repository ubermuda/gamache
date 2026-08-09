# Gamache CLI checks

These checks run through the `gamache` CLI. Register the ones you want in `gamache.php` (see the [README](../README.md#the-gamache-cli) for setup, output format, and exit codes).

All checks live in the `Gamache\Check` namespace.

- [CommentBudgetCheck](#commentbudgetcheck)
- [FormTypeTranslationKeysCheck](#formtypetranslationkeyscheck)
- [MessengerRoutingCheck](#messengerroutingcheck)
- [NoArbitraryValuesCheck](#noarbitraryvaluescheck)
- [NoTodosCheck](#notodoscheck)
- [PageTitleBrandNameCheck](#pagetitlebrandnamecheck)
- [ServicesYamlCheck](#servicesyamlcheck)
- [ServiceTagNamesCheck](#servicetagnamescheck)
- [TranslationCheck](#translationcheck)
- [TranslationParityCheck](#translationparitycheck)
- [TurboStreamTargetsCheck](#turbostreamtargetscheck)
- [XlfPluralizationCheck](#xlfpluralizationcheck)

---

## CommentBudgetCheck

Flags runs of consecutive comment lines longer than a budget. A long comment is usually a decision log — the investigation, the alternatives weighed, the benchmark numbers — which belongs in the commit message or pull request, leaving only the constraint a reader needs at that line.

**Scans:** `src/**/*.php`, `tests/**/*.php`, `config/**/*.yaml`, `templates/**/*.twig`, `assets/**/*.js`, `assets/**/*.css` (configurable). Comment syntax follows the file extension; anything unrecognised is read as `#`-commented, which covers YAML, dotenv, justfiles, Dockerfiles and shell. PHP is tokenised, so `//` inside a string literal is never counted. Paths under `vendor/` or `node_modules/` are skipped.

**Severity:** Warning by default, so the run still exits 0 — `Comment block of 13 lines exceeds the 5-line budget; keep the constraint here and move the reasoning to the commit message`. Pass `Severity::Error` to make the budget binding.

**Options:**

- `maxLines` (default `5`) — the longest run that passes.
- `patterns` (default as above) — pass a list to scan other files, including extensionless ones such as `justfile`.
- `severity` (default `Severity::Warning`) — pass `Severity::Error` to fail the run on an unsuppressed block.

A line count cannot tell a good six-line comment from a bad one, which is why the default only advises. `@comment-budget-ignore` is what supplies that judgment, so a project willing to mark its deliberate long blocks can raise the severity and have every remaining one be an oversight. Advisory output is easy to stop reading: a check that cannot fail is a check whose green result means nothing.

**Exemptions:** PHP docblocks and JSDoc `/**` blocks, whose length is driven by annotations rather than prose. A shebang does not open a block. Blank lines do not break a run, since a comment split by one still reads as a single block. Suppress a genuine false positive with `@comment-budget-ignore` anywhere in the block.

```php
// BAD — the reasoning outlives its usefulness in the file
// The hourly sweep runs three candidate queries, none of which shared
// indexed columns before this: findExpiredTrials() and
// findTrialEndedSubscribers() both filter on (status, trial_ends_at);
// findCanceledPastPeriod() filters on (status, current_period_end)
// instead. Partial indexes would be tighter, but Postgres rewrites the
// predicate on storage and DBAL's comparator does not normalize it back.

// GOOD — the trap survives, the reasoning moves to the PR
// Partial indexes don't round-trip through DBAL's comparator (Postgres
// rewrites the predicate), so migrate-diff never settles. Keep these plain.
```

---

## FormTypeTranslationKeysCheck

Ensures user-facing form options (`label`, `help`, `placeholder`, …) in FormType classes use translation keys with a module-based prefix.

**Scans:** `src/Module/**/*FormType.php` (configurable).

The check derives the required prefix from the file path and class name. For `src/Module/GitHub/Form/ImportGitHubRepoFormType.php`, the module is `GitHub` and the block prefix is the snake_cased class name without its `FormType`/`Type` suffix — so every user-facing option must start with `github.form.import_git_hub_repo_form.`.

**Severity:** Error — `key must start with '<module>.form.<block_prefix>.'`

**Options:**

| Option | Type | Default |
|---|---|---|
| `filePattern` | `string` | `'src/Module/**/*FormType.php'` |
| `moduleExtractPattern` | `?string` | `null` (uses `#/Module/([^/]+)/#`) |
| `userFacingOptions` | `list<string>` | `['label', 'help', 'placeholder', 'invalid_message', 'choice_label']` |

**Exemptions:** dynamic values (variables, concatenations) and non-string values are skipped. Files whose path doesn't match the module pattern are skipped.

```php
// BAD — wrong prefix
$builder->add('name', TextType::class, [
    'label' => 'wrong.form.create_project_form.name',
]);

// GOOD
$builder->add('repoUrl', TextType::class, [
    'label' => 'github.form.import_git_hub_repo_form.repo_url',
]);
```

---

## MessengerRoutingCheck

Ensures every `App\` class routed in Messenger configuration actually exists.

**Scans:** `config/packages/messenger.yaml`. Each key under `framework.messenger.routing` that starts with `App\` must map to an existing file under `src/`.

**Severity:** Error — `Class '<FQCN>' not found (expected src/<path>.php)`

**Options:** none.

**Exemptions:** classes outside the `App\` namespace are ignored.

```yaml
# BAD — src/Message/Nonexistent.php does not exist
framework:
    messenger:
        routing:
            App\Message\Nonexistent: async

# GOOD
framework:
    messenger:
        routing:
            App\Message\SendWelcomeEmail: async
```

---

## NoArbitraryValuesCheck

Flags arbitrary Tailwind values (`w-[100px]`, `text-[#ff0000]`, `@apply w-[45rem]`) in templates, JS, and CSS. Use semantic classes or named Tailwind tokens instead.

**Scans:**

- `templates/**/*.twig` and `assets/**/*.js` for `<prefix>-[...]` and `[var(...)]` patterns
- `assets/styles/app.css` for `@apply` directives containing bracketed numeric values

**Severity:** Error — `Arbitrary Tailwind value found; use a semantic class or named Tailwind token instead`

**Options:**

| Option | Type | Default |
|---|---|---|
| `ignoredFiles` | `list<string>` | `[]` — paths relative to the project root, skipped entirely |

**Exemptions:** add an `@arbitrary-value-ignore` comment on the offending line.

```twig
{# BAD #}
<div class="w-[100px] flex">

{# GOOD #}
<div class="flex items-center gap-4">
```

---

## NoTodosCheck

Rejects `TODO`, `FIXME`, `XXX`, and `@todo` markers in source code: track follow-up work outside the codebase.

**Scans:** `src/**/*.php`, line by line.

**Severity:** Error — `TODO/FIXME/XXX comment found; move follow-up work to a tracking file`

**Options:** none. **Exemptions:** none. Matching is case-sensitive: lowercase `todo` without the `@` prefix doesn't trigger.

```php
// BAD
// TODO: implement this properly

// GOOD
// Tracked in PROJ-123
```

---

## PageTitleBrandNameCheck

Keeps the brand name out of page-title translation values. A page title is composed in the template from two translated strings — `{{ 'x.page.title'|trans }} — {{ 'app.name'|trans }}` — so the `*.page.title` value itself must carry only the page name.

**Scans:** `translations/messages.*.xlf`. Any `<trans-unit>` whose id ends in `.page.title` is flagged when its `<target>` contains the brand or a ` — ` separator. The brand is read from the same file's `app.name` target **per locale**, never hard-coded.

**Severity:** Error — `Page-title translation "<key>" must contain only the page name. …`

**Options:** none. **Exemptions:** trans-units whose id does not end in `.page.title`.

```xml
<!-- BAD — brand and separator baked into the value -->
<trans-unit id="login.page.title"><target>Sign in — Better Plans</target></trans-unit>

<!-- GOOD — page name only; the template appends " — {{ 'app.name'|trans }}" -->
<trans-unit id="login.page.title"><target>Sign in</target></trans-unit>
```

---

## ServicesYamlCheck

Prohibits legacy constructs in `config/services.yaml` in favor of PHP attributes.

**Scans:** `config/services.yaml`. Two constructs are flagged:

| Construct | Message |
|---|---|
| `_instanceof:` block | `_instanceof blocks are not allowed; use #[AutoconfigureTag('app.tag')] on the interface instead` |
| `arguments:` on an `App\` service | `Explicit arguments: blocks are not allowed for App\ services; use #[Autowire(env: '...')] on the constructor parameter instead` |

The `arguments:` ban only targets services whose class is in the `App\` namespace
(either the definition key, or its `class:` if set). You own those constructors, so
configure them with `#[Autowire]` attributes instead. **Third-party services are
exempt** — you cannot annotate a constructor you do not own, so an explicit
`arguments:` block is the only mechanism available to configure a bundle's class.

**Severity:** Error. **Options:** none.

```yaml
# BAD — App\ class, configure via attributes instead
services:
    App\SomeService:
        arguments:
            $foo: '%env(FOO)%'

# GOOD — keep App\ services minimal, configure via attributes
services:
    _defaults:
        autowire: true
        autoconfigure: true
    App\:
        resource: '../src/'

# ALSO GOOD — third-party class you cannot annotate
services:
    Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware:
        arguments:
            $allowedHosts: '%env(csv:MCP_ALLOWED_HOSTS)%'
```

---

## ServiceTagNamesCheck

Requires the `app.` prefix on all service tags, so application tags are distinguishable from framework tags.

**Scans:**

- `src/**/*.php` — tag strings in `#[AutoconfigureTag]`, `#[AutowireIterator]`, and `#[AutowireTagged]` attributes
- `config/services.yaml` — the `tags:` key of service definitions

**Severity:** Error — `Service tag '<tag>' must use the 'app.' prefix`

**Options:** none. **Exemptions:** none.

```php
// BAD
#[AutoconfigureTag('my_handler')]
interface HandlerInterface {}

// GOOD
#[AutoconfigureTag('app.my_handler')]
interface HandlerInterface {}
```

---

## TranslationCheck

Detects hardcoded user-facing prose in PHP and Twig files using a scoring heuristic. Strings that look like human sentences should be translation keys instead.

**Scans:** `src/**/*.php` (token-level analysis) and `templates/**/*.twig`.

**Scoring:** each string literal gets a score; scores at or above the threshold are reported.

| Signal | Points |
|---|---|
| Space between word characters | +3 |
| Starts with an uppercase letter | +1 |
| Ends with `.`, `?`, `!`, or `:` | +1 |
| Longer than 15 characters | +1 |
| Contains function words (the, your, is, are, …) | +1 |
| All lowercase | −2 |
| Starts with a non-letter | −2 |
| Contains key-like separators (hyphens, colons, slashes, …) | −2 |

**Severity:** Warning (advisory) — `Score <n>  '<string>'`. This check never fails the build; it points at suspects.

**Options:**

| Option | Type | Default | Effect |
|---|---|---|---|
| `threshold` | `int` | `3` | Minimum score to report |
| `ignoredCallSites` | `list<string\|\Closure>` | `[]` | Constructor/method call sites to skip (e.g. `'LoggerInterface::info'`) |
| `ignoreExceptionClasses` | `bool` | `true` | Skip strings passed to exception constructors |
| `ignoredSourceNamespaces` | `list<string>` | `[]` | FQCN glob patterns; matching files are skipped entirely (e.g. `'App\\**\\Repository\\*'`) |
| `safeAttributeNamespaces` | `list<string>` | `[]` | FQCN glob patterns; string arguments of matching attributes are skipped (e.g. `'Doctrine\\ORM\\Mapping\\*'`) |
| `safeTwigFunctions` | `list<string>` | `[]` | Twig filter/function names whose string arguments are skipped (e.g. `'date'`) |

Strings that already look like translation keys, and strings containing `printf`-style format specifiers, are skipped automatically.

```php
// BAD — score 5+: prose belongs in the translation catalog
return 'Sign in to your account';

// GOOD — looks like a key, scores below threshold
return 'account.sign_in.title';
```

---

## TranslationParityCheck

Ensures every translation key exists in every locale.

**Scans:** `translations/messages.*.xlf`. The locale comes from the filename (`messages.fr.xlf` → `fr`). After collecting all files, the check reports each key present in one locale but missing from another.

**Severity:** Error — `Key '<key>' is missing from locale '<locale>'`

**Options:** none. **Exemptions:** none. With fewer than two locale files there is nothing to compare, so the check passes.

---

## TurboStreamTargetsCheck

Ensures every static `<turbo-stream target="...">` points at an element with a matching static `id="..."` somewhere in your templates.

**Scans:** `templates/**/*.html.twig`. The check first collects all static `id` attributes across every template, then verifies each `turbo-stream` target against that set.

**Severity:** Error — `Turbo stream target="<target>" has no matching id="<target>" in any template.`

**Options:** none.

**Exemptions:** dynamic targets and ids (containing `{{` or `{%`) are skipped.

```twig
{# layout.html.twig #}
<div id="user-profile"></div>

{# BAD — no element with id="missing" anywhere #}
<turbo-stream action="replace" target="missing">…</turbo-stream>

{# GOOD #}
<turbo-stream action="replace" target="user-profile">…</turbo-stream>
```

---

## XlfPluralizationCheck

Ensures plural translations handle the zero case.

**Scans:** `translations/messages.*.xlf`. Any `<target>` containing a pipe (`|`, Symfony's plural-form separator) must also contain a `{0}` or `=0` branch.

**Severity:** Error — `Translation key "<key>" has plural form but is missing a {0} or =0 (zero-count) case.`

**Options:** none. **Exemptions:** non-plural targets (no pipe) are skipped.

```xml
<!-- BAD — no zero case -->
<target>{1} One item|[2,Inf] %count% items</target>

<!-- GOOD -->
<target>{0} No items|{1} One item|[2,Inf] %count% items</target>
```
