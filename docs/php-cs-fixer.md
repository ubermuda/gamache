# PHP-CS-Fixer fixers

Two custom fixers in the `Gamache\PhpCsFixer` namespace. Both are safe (non-risky) and fix code automatically.

## Recommended setup

Register via the `Fixers` aggregate in `.php-cs-fixer.dist.php`:

```php
use Gamache\PhpCsFixer\Fixers;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new Fixers())
    ->setRules([
        '@Symfony' => true,
        ...Fixers::rules(),
    ]);
```

Referencing `Fixers` instead of listing rules by hand means new gamache fixers and rule updates apply automatically when you `composer update`.

`Fixers::rules()` enables, on top of the two custom fixers:

| Rule | Config | Why |
|---|---|---|
| `Gamache/multiline_attribute` | `attributes: ['Route']`, `minimum_arguments: 3` | Expand `#[Route(...)]` with 3+ arguments to one per line |
| `multiline_promoted_properties` | `true` | One promoted constructor property per line |
| `php_unit_method_casing` | `snake_case` | Test methods read as sentences (overrides the `@Symfony` camelCase default) |
| `ordered_attributes` | `true` | Alphabetical attribute ordering |

Spread `...Fixers::rules()` *after* your base ruleset (`@Symfony`) so the snake_case override wins, and list any per-rule overrides after it.

---

## BlankLineBetweenAttributedParametersFixer

**Rule name:** `Gamache/blank_line_between_attributed_parameters`

Separates attributed constructor parameters with a blank line, so each attribute visually belongs to its parameter.

```php
// BEFORE
public function __construct(
    #[Bar]
    public string $a,
    #[Baz]
    public string $b,
) {}

// AFTER
public function __construct(
    #[Bar]
    public string $a,

    #[Baz]
    public string $b,
) {}
```

**Options:** none.

---

## MultilineAttributeFixer

**Rule name:** `Gamache/multiline_attribute`

Expands the arguments of configured attributes to one per line, with a trailing comma.

```php
// BEFORE
#[Route('/path', name: 'route_name', methods: ['GET'])]

// AFTER
#[Route(
    '/path',
    name: 'route_name',
    methods: ['GET'],
)]
```

**Options:**

| Option | Type | Default | Effect |
|---|---|---|---|
| `attributes` | `string[]` | `['Route']` | Short names of attributes to expand |
| `minimum_arguments` | `int` (>= 1) | `1` | Expand only attributes with at least this many arguments |

```php
'Gamache/multiline_attribute' => [
    'attributes' => ['Route', 'IsGranted'],
    'minimum_arguments' => 2,
],
```
