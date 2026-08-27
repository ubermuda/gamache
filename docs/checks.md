# Gamache CLI checks

These checks run through the `gamache` CLI. Register the ones you want in `gamache.php` (see the [README](../README.md#the-gamache-cli) for setup, output format, and exit codes).

All checks live in the `Gamache\Check` namespace.

- [CommentBudgetCheck](#commentbudgetcheck)
- [DeploymentConfigParityCheck](#deploymentconfigparitycheck)
- [FormTypeTranslationKeysCheck](#formtypetranslationkeyscheck)
- [MessengerRoutingCheck](#messengerroutingcheck)
- [NoArbitraryValuesCheck](#noarbitraryvaluescheck)
- [NoTodosCheck](#notodoscheck)
- [PageTitleBrandNameCheck](#pagetitlebrandnamecheck)
- [ServicesYamlCheck](#servicesyamlcheck)
- [ServiceTagNamesCheck](#servicetagnamescheck)
- [SkillReferenceCheck](#skillreferencecheck)
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

## DeploymentConfigParityCheck

Ensures every deployment variable a project declares also reaches the file an operator copies. A variable can be declared, consumed and deployed while the template that documents it never mentions it: `terraform validate` passes either way, because the tfvars example is comment-only and nothing reads it, and Compose starts fine, because an env file supplies *interpolation values* without injecting them into containers. The option is then real but undiscoverable — or discoverable, set, and silently ignored.

**Scans:** two independent pairs, both configurable and both skipped when either half is absent (most projects have no `terraform/` directory, and that is not a violation):

| Declared in | Must be named in |
|---|---|
| `terraform/variables.tf` | `terraform/terraform.tfvars.example` |
| `terraform/terraform.tfvars.example` | `terraform/variables.tf` |
| `docker/compose/prod.env.example` | `docker/compose/prod.yaml` |
| `docker/compose/prod.yaml` (settable keys) | `docker/compose/prod.env.example` |
| `.env` + `%env(...)%` in `config/`, `src/` | assigned in `terraform/`, named in `docker/compose/prod.yaml` |

For Terraform, every `variable "name" {` must appear somewhere in the tfvars example. **A commented-out example counts** — that file is documentation, so `# export_storage_key = "..."` is exactly the right way to document an optional variable.

For Compose, every assignment in the env-file template must be referenced somewhere in the Compose file. The match is whole-file rather than scoped to the `x-app-environment` block, because plenty of keys are legitimately interpolation-only: `POSTGRES_PASSWORD` gets folded into `DATABASE_URL`, `COMPOSE_PROJECT_NAME` names the stack. What the check rules out is the key that appears *nowhere* — the one an operator can set and watch do nothing. Commented-out assignments count as documented: an optional variable an operator uncomments is just as broken as a required one.

Names match on whole-word boundaries, so a `region` variable is not considered documented by a line that only mentions `db_cluster_region`.

**Severity:** Error — `Terraform variable "export_storage_key" is declared in terraform/variables.tf but never named in terraform/terraform.tfvars.example, so an operator copying the template cannot discover it. A commented-out example counts.` The violation is reported against the file the name is missing from, which is the file you have to edit.

**Options:**

| Option | Type | Default |
|---|---|---|
| `terraformVariablesPath` | `string` | `'terraform/variables.tf'` |
| `terraformExamplePath` | `string` | `'terraform/terraform.tfvars.example'` |
| `composeEnvExamplePath` | `string` | `'docker/compose/prod.env.example'` |
| `composeFilePath` | `string` | `'docker/compose/prod.yaml'` |
| `ignoredTerraformVariables` | `list<string>` | `[]` |
| `ignoredEnvKeys` | `list<string>` | `[]` |
| `appEnvPath` | `string` | `'.env'` |
| `envReferenceDirs` | `list<string>` | `['config', 'src']` |
| `terraformDir` | `string` | `'terraform'` |
| `terraformReportPath` | `string` | `'terraform/main.tf'` |
| `moduleProvidedEnvKeys` | `list<string>` | `[]` |
| `ignoredAppEnvKeys` | `list<string>` | `[]` |

Use the ignore lists for variables a project deliberately keeps out of its template — credentials supplied only through `TF_VAR_*`, for instance.

### What the application reads

The file pairs above only compare deployment files to each other, so they are all equally consistent when a variable reaches no deployment at all. That is the failure that shipped twice in the project this check came from: a variable consumed by the application, declared nowhere in `terraform/`, and therefore invisible to every pair.

The application's own set is the union of `appEnvPath` and every `%env(...)%` placeholder under `envReferenceDirs`, because neither is complete: a variable can be resolved from a placeholder and never appear in the dotenv, and placeholders live in `#[Autowire]` attributes under `src/` as well as in `config/`. The name is taken as the last colon-separated segment, so processors and a named parameter fallback do not hide it — `%env(default:app.trusted_proxies_default:TRUSTED_PROXIES)%` reads as `TRUSTED_PROXIES`.

Each name must then be *assigned* somewhere in `terraformDir` — as `NAME = {` in an env map or `key = "NAME"` in an env block — and named somewhere in `composeFilePath`. Assignment rather than mention, because a Terraform variable's `description` routinely names the environment variable it feeds, so a text search is satisfied by prose while nothing wires the value through. Dot-directories are skipped throughout, so a project that has run `terraform init` does not have the downloaded source of its modules — or the examples they ship — counted as its own wiring. Two escape hatches, both deliberately narrow:

- `moduleProvidedEnvKeys` — injected by an external Terraform module, so absent from this repository's `.tf` files by design. Transcribe it from the module at the ref you pin; nothing can derive it for you, and only some module *arguments* become environment variables.
- `ignoredAppEnvKeys` — read but deliberately reaching no deployment, either development-only or already correct at the committed dotenv value.

`ignoredAppEnvKeys` is itself checked: a name the application no longer reads is reported as a stale exemption. Without that, the list becomes the place a missing variable hides, since adding a name to it silences the check just as well as wiring the variable up. `moduleProvidedEnvKeys` is exempt from that, because it describes the module rather than this application and a module may well inject names the application never reads — which means a pin bump that changes the module's own set is not caught, and re-reading it is on you.

For Compose, what counts as *settable* is an interpolation in any of its `${V}`, `${V:-default}` and `${V:?message}` forms, or a bare `KEY:` passing the host's value through. A literal like `APP_ENV: prod` is not something an operator can set, so requiring it to be documented would be noise.

```terraform
# BAD — variables.tf declares it, main.tf consumes it, the template never names it
variable "export_storage_key" {
  type      = string
  sensitive = true
}
```

```terraform
# GOOD — terraform.tfvars.example, where an operator will actually see it
# export_storage_key = "..."   # optional: S3-compatible export storage
```

```bash
# BAD — prod.env.example documents it, prod.yaml never interpolates it,
# so an operator sets it and nothing happens
MERCURE_JWT_SECRET=
```

```yaml
# GOOD — prod.yaml, where setting it has an effect
x-app-environment: &app-environment
  MERCURE_JWT_SECRET: "${MERCURE_JWT_SECRET:?set it in docker/compose/prod.env}"
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

## SkillReferenceCheck

Reports references in agent skill files that no longer resolve: a `just` recipe the justfile does not define, and a file path that does not exist.

A skill tells an agent which command to run and which file to open, and nothing links it to the thing it names. Rename a recipe or move a directory and the skill still reads as authoritative — the next session follows an instruction that cannot work, and it surfaces as the agent improvising rather than as a broken build. Nothing else catches this: skills are prose, so no compiler, linter or test suite ever reads them.

**Scans:** `.claude/skills/*/SKILL.md` (configurable). Only fenced blocks and inline code spans are read, because a skill is prose about a codebase — `just cs` is a command, "just a moment" is English.

**Severity:** Error by default — *References `just deploy-prod`, which justfile does not define. Rename the reference or restore the recipe.*

**Options:**

- `patterns` (default `.claude/skills/*/SKILL.md`) — the files to scan. Point it at `.claude/commands/*.md` or a docs directory to cover those too.
- `justfilePath` (default `justfile`) — where recipes are declared. When the file is absent, recipe references are left alone rather than all reported as missing.
- `pathPrefixes` (default `assets/`, `bin/`, `config/`, `docs/`, `e2e/`, `migrations/`, `public/`, `templates/`, `translations/`) — only tokens starting with one of these are read as a file reference. Everything else in a code span is prose, a flag, or somebody else's path.
- `ignoredRecipes` — recipe names a skill may name although the justfile does not define them, e.g. one a plugin supplies.
- `ignoredPaths` — paths a skill may name although they are absent, e.g. one the project generates or gitignores.
- `severity` (default `Severity::Error`).

**What counts as a reference.** A `just` token names a recipe only in command position — at the start of a code span or after `&&`, `||`, `;`, `|` or `(`. Recipe parameters, dependencies and `alias x := y` are all read from the justfile; `set` directives and `name := value` assignments are not recipes and are reported when a skill names one. A path token containing a placeholder, glob or variable (`<`, `>`, `{`, `}`, `$`, `%`, `*`, `?`) is a shape rather than a path and is skipped.

`src/` and `tests/` are deliberately absent from the default prefixes. Skills illustrate naming conventions with paths that were never meant to resolve — a module called X importing from a module called Y, a `CreateIssueHandler.php` that only shows where a handler goes. Pointed at a real project's skills, every finding under `src/` was one of those and none was rot. Add the prefix if your skills cite only files that exist.

```markdown
<!-- BAD — the recipe was renamed to `worktree-up` two months ago -->
Provision the tree with `just worktree-create`.

<!-- GOOD -->
Provision the tree with `just worktree-up`.
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
