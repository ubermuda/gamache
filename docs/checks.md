# Gamache CLI checks

These checks run through the `gamache` CLI. Register the ones you want in `gamache.php` (see the [README](../README.md#the-gamache-cli) for setup, output format, and exit codes).

All checks live in the `Gamache\Check` namespace.

- [FormTypeTranslationKeysCheck](#formtypetranslationkeyscheck)
- [MessengerRoutingCheck](#messengerroutingcheck)
- [NoArbitraryValuesCheck](#noarbitraryvaluescheck)
- [NoTodosCheck](#notodoscheck)
- [ServicesYamlCheck](#servicesyamlcheck)
- [ServiceTagNamesCheck](#servicetagnamescheck)
- [TranslationCheck](#translationcheck)
- [TranslationParityCheck](#translationparitycheck)
- [TurboStreamTargetsCheck](#turbostreamtargetscheck)
- [XlfPluralizationCheck](#xlfpluralizationcheck)

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

## ServicesYamlCheck

Prohibits legacy constructs in `config/services.yaml` in favor of PHP attributes.

**Scans:** `config/services.yaml`. Two constructs are flagged:

| Construct | Message |
|---|---|
| `_instanceof:` block | `_instanceof blocks are not allowed; use #[AutoconfigureTag('app.tag')] on the interface instead` |
| `arguments:` on a service | `Explicit arguments: blocks are not allowed; use #[Autowire(env: '...')] on the constructor parameter instead` |

**Severity:** Error. **Options:** none. **Exemptions:** none.

```yaml
# BAD
services:
    App\SomeService:
        arguments:
            $foo: '%env(FOO)%'

# GOOD — keep services.yaml minimal, configure via attributes
services:
    _defaults:
        autowire: true
        autoconfigure: true
    App\:
        resource: '../src/'
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
