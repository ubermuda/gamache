# Twig-CS-Fixer rules

Six custom rules in the `Gamache\TwigCsFixer` namespace for [twig-cs-fixer](https://github.com/VincentLanglet/Twig-CS-Fixer) (3.x and 4.x). They report violations; none rewrites your templates.

## Recommended setup

Register all gamache twig rules via `GamacheStandard` in `.twig-cs-fixer.php`:

```php
use Gamache\TwigCsFixer\GamacheStandard;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Ruleset\Ruleset;

$ruleset = new Ruleset();
$ruleset->addStandard(new GamacheStandard());

return (new Config())->setRuleset($ruleset);
```

Using `GamacheStandard` means new gamache twig rules apply automatically when you `composer update`.

---

## CsrfTokenValueRule

A CSRF token input must get its value from `csrf_token()`, not a literal string. A hard-coded value fails CSRF validation at runtime.

The rule flags `<input>` elements with `name="_csrf_token"` whose `value` attribute is a literal (handles multi-line inputs and either attribute order).

> `CSRF token input value must be a Twig expression: value="{{ csrf_token('...') }}".`

```twig
{# BAD #}
<input type="hidden" name="_csrf_token" value="delete-project">

{# GOOD #}
<input type="hidden" name="_csrf_token" value="{{ csrf_token('delete-project') }}">
```

---

## IncludeOnlyRule

Every `{% include %}` must use the `only` keyword, so the included template receives explicit variables instead of the full parent context.

> `{% include %} must use "only" to prevent variable leakage into the included template.`

```twig
{# BAD #}
{% include 'components/_card.html' %}

{# GOOD #}
{% include 'components/_card.html' only %}
{% include 'components/_card.html' with {title: 'project.card.title'} only %}
```

---

## InlineSvgRule

Prohibits inline `<svg>` elements. Use the Symfony UX Icon component instead, which keeps icons in one place.

> `Inline <svg> elements are not allowed. Use <twig:UX:Icon name="lucide:..." /> instead.`

```twig
{# BAD #}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2"/></svg>

{# GOOD #}
<twig:UX:Icon name="lucide:check" class="w-4 h-4" />
```

---

## ModuleTemplateNamespaceRule

Template paths under the module template root must be referenced through their Twig namespace, not as plain `Module/<Module>/...` paths.

Checks string literals in `{% extends %}`, `{% include %}`, `{% embed %}`, `{% from %}`, `{% import %}`, and `{% use %}` tags. The match is conservative — `Module/` followed by a PascalCase segment — so templates in repos without that layout never match. The `{{ include(...) }}` *function* form is not covered. The PHP-side counterpart for `render()` / `TemplatedEmail` calls is the PHPStan rule of the same name (see [docs/phpstan-rules.md](phpstan-rules.md#moduletemplatenamespacerule)).

> `Template "Module/Event/show.html.twig" must be referenced through its Twig namespace: "@Event/show.html.twig".`

```twig
{# BAD #}
{% extends 'Module/Event/show.html.twig' %}
{% include 'Module/Rsvp/_card.html.twig' only %}

{# GOOD #}
{% extends '@Event/show.html.twig' %}
{% include '@Rsvp/_card.html.twig' only %}
```

---

## TranslationKeyRule

All displayable text must go through the translation system. The rule reports two kinds of violations:

1. **Invalid keys passed to `|trans`** — the string must match `/^[a-z][a-z0-9]*([._-][a-z0-9]+)*$/` (e.g. `account.login.heading`). Prose like `'Welcome back'` is rejected.
2. **Raw text in templates** — visible text outside HTML tags (entities decoded, attribute values ignored to avoid false positives on Stimulus descriptors).

> `String "<value>" passed to |trans must be a translation key (e.g. "account.login.heading").`
> `Raw text "<text>" found in template. Wrap it in a translation key and use |trans.`

```twig
{# BAD #}
{{ 'Welcome back'|trans }}
<button>Submit</button>

{# GOOD #}
{{ 'account.login.heading'|trans }}
<button>{{ 'form.submit_button'|trans }}</button>
```

**Options:** none — the key pattern is fixed.

---

## TransPlaceholderEscapeRule

The `{% trans with %}` **tag** bypasses Twig autoescaping, so non-literal placeholder values must be escaped with `|e`. The `|trans` **filter** is the opposite: its output is autoescaped, so `|e` on a placeholder double-escapes (apostrophes render as `&#039;`).

Two checks, applied to `*.html.twig` templates only — plain-text templates (emails, chat messages) have autoescaping off, and `|e` there would corrupt the output with HTML entities:

- a non-literal placeholder value in a literal `{% trans with {...} %}` hash without `|e`/`|escape` is flagged. String and number literals pass; a non-literal hash (`{% trans with vars %}`) cannot be inspected statically and is skipped.
- `|e`/`|escape` inside a `|trans({...})` argument is flagged, but only when the expression is rendered directly in a `{{ ... }}` output — `{% set %}` assignments and `|raw` pipelines keep their pre-escaping, since there the manual escape *is* the protection.

Numeric variables (e.g. a `%count%`) in a tag hash are flagged too — adding `|e` is harmless, and a deterministic rule beats a fuzzy "user-ish variable" heuristic. Suppress a guaranteed-safe value with `{# twig-cs-fixer-disable-next-line TransPlaceholderEscape #}`.

> `Placeholder %name% in a {% trans with %} tag must be escaped with |e — the trans tag bypasses autoescaping.`
> `Placeholder passed to the |trans filter must not use |e — the filter output is autoescaped, so this double-escapes.`

```twig
{# BAD — tag placeholders are not autoescaped #}
{% trans with {'%name%': user.name} %}greeting{% endtrans %}

{# GOOD #}
{% trans with {'%name%': user.name|e} %}greeting{% endtrans %}

{# BAD — filter output is autoescaped; |e double-escapes #}
{{ 'greeting'|trans({'%name%': user.name|e}) }}

{# GOOD #}
{{ 'greeting'|trans({'%name%': user.name}) }}
```
