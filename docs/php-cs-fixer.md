# PHP-CS-Fixer fixers

Two custom fixers in the `Gamache\PhpCsFixer` namespace. Both are safe (non-risky) and fix code automatically.

Register them in `.php-cs-fixer.dist.php`:

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
        'Gamache/multiline_attribute' => true,
    ]);
```

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
