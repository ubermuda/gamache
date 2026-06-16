# Tool Presets (Aggregates) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers-extended-cc:subagent-driven-development (recommended) or superpowers-extended-cc:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship one gamache-owned aggregate per external tool (PHP-CS-Fixer, Rector, Twig-CS-Fixer) so consumers enable all gamache conventions in one reference and receive new rules automatically on `composer update`.

**Architecture:** Each tool gets a single aggregate. Rector and Twig-CS-Fixer use their native aggregate mechanisms (`SetList` constant + set file; `StandardInterface`). PHP-CS-Fixer has no usable native set on `^3.0`, so a `Fixers` collection (`IteratorAggregate`) plus a static `rules()` map. The custom-fixer rule names in `rules()` are kept in sync with the collection by a test, so they cannot drift. Each aggregate bundles gamache's own code plus the curated built-in rules (`ordered_attributes`; Rector's `SortCallLikeNamedArgsRector` + `SortAttributeNamedArgsRector`).

**Tech Stack:** PHP 8.5, friendsofphp/php-cs-fixer ^3, rector/rector ^2, vincentlanglet/twig-cs-fixer ^3, PHPUnit ^13. Tests: `vendor/bin/phpunit`. Static analysis: `vendor/bin/phpstan analyse --no-progress`.

---

### Task 1: PHP-CS-Fixer `Fixers` aggregate

**Goal:** A `Fixers` collection of gamache's custom fixers plus a `rules()` map (custom-fixer rules + `ordered_attributes`), with a test guaranteeing the rule map and the collection never drift.

**Files:**
- Create: `src/PhpCsFixer/Fixers.php`
- Test: `tests/PhpCsFixer/FixersTest.php`

**Acceptance Criteria:**
- [ ] `new Fixers()` is iterable and yields one instance of each custom fixer.
- [ ] `Fixers::rules()` returns the two custom-fixer rules plus `'ordered_attributes' => true`.
- [ ] Every `Gamache/…` key in `Fixers::rules()` matches a fixer's `getName()` in the collection.

**Verify:** `vendor/bin/phpunit tests/PhpCsFixer/FixersTest.php` → OK (3 tests)

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/PhpCsFixer/FixersTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\Tests\PhpCsFixer;

use Gamache\PhpCsFixer\Fixers;
use PhpCsFixer\Fixer\FixerInterface;
use PHPUnit\Framework\TestCase;

final class FixersTest extends TestCase
{
    public function test_collection_yields_custom_fixers(): void
    {
        $fixers = iterator_to_array(new Fixers());

        self::assertNotEmpty($fixers);
        foreach ($fixers as $fixer) {
            self::assertInstanceOf(FixerInterface::class, $fixer);
        }
    }

    public function test_rules_include_ordered_attributes(): void
    {
        self::assertSame(true, Fixers::rules()['ordered_attributes'] ?? null);
    }

    public function test_every_custom_rule_resolves_to_a_registered_fixer(): void
    {
        $names = array_map(
            static fn (FixerInterface $f): string => $f->getName(),
            iterator_to_array(new Fixers()),
        );

        foreach (array_keys(Fixers::rules()) as $rule) {
            if (!str_starts_with($rule, 'Gamache/')) {
                continue; // built-in rule, no custom fixer needed
            }
            self::assertContains($rule, $names, sprintf('Rule "%s" has no registered fixer', $rule));
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/PhpCsFixer/FixersTest.php`
Expected: FAIL — `Gamache\PhpCsFixer\Fixers` not found.

- [ ] **Step 3: Write the implementation**

Create `src/PhpCsFixer/Fixers.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\PhpCsFixer;

use PhpCsFixer\Fixer\FixerInterface;

/**
 * Aggregate of every gamache PHP-CS-Fixer custom fixer and its recommended
 * rule configuration. Reference this from .php-cs-fixer.dist.php so new gamache
 * rules arrive automatically on `composer update` without editing the config.
 *
 * @implements \IteratorAggregate<int, FixerInterface>
 */
final class Fixers implements \IteratorAggregate
{
    /**
     * Custom fixer instances, for ConfigInterface::registerCustomFixers().
     *
     * @return list<FixerInterface>
     */
    public static function all(): array
    {
        return [
            new BlankLineBetweenAttributedParametersFixer(),
            new MultilineAttributeFixer(),
        ];
    }

    /**
     * Recommended rule map: gamache custom-fixer rules plus curated built-ins.
     *
     * @return array<string, array<string, mixed>|bool>
     */
    public static function rules(): array
    {
        return [
            'Gamache/blank_line_between_attributed_parameters' => true,
            'Gamache/multiline_attribute' => ['attributes' => ['Route'], 'minimum_arguments' => 1],
            'ordered_attributes' => true,
        ];
    }

    /**
     * @return \Iterator<int, FixerInterface>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator(self::all());
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/PhpCsFixer/FixersTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/PhpCsFixer/Fixers.php tests/PhpCsFixer/FixersTest.php
git commit -m "feat(php-cs-fixer): Fixers aggregate (collection + rules map)"
```

---

### Task 2: Rector `GamacheSetList::CONVENTIONS` set

**Goal:** A `GamacheSetList` constant pointing to a set file that registers the custom `InjectRepository…` rule plus the two built-in named-argument sorters, verified end-to-end through `withSets()`.

**Files:**
- Create: `src/Rector/GamacheSetList.php`
- Create: `src/Rector/config/conventions.php`
- Create: `tests/Rector/GamacheConventionsSet/GamacheConventionsSetTest.php`
- Create: `tests/Rector/GamacheConventionsSet/config/configured_set.php`
- Create: `tests/Rector/GamacheConventionsSet/Fixture/sorts_named_args.php.inc`

**Acceptance Criteria:**
- [ ] `GamacheSetList::CONVENTIONS` is a readable file path.
- [ ] Loading the set via `RectorConfig::configure()->withSets([GamacheSetList::CONVENTIONS])` applies `SortCallLikeNamedArgsRector` (named args get reordered to declaration order).

**Verify:** `vendor/bin/phpunit tests/Rector/GamacheConventionsSet/GamacheConventionsSetTest.php` → OK (1 test)

**Steps:**

- [ ] **Step 1: Write the set list class**

Create `src/Rector/GamacheSetList.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\Rector;

/**
 * Set list of gamache Rector conventions. Reference via
 * `->withSets([GamacheSetList::CONVENTIONS])` so new gamache rules arrive
 * automatically on `composer update`.
 */
final class GamacheSetList
{
    public const CONVENTIONS = __DIR__.'/config/conventions.php';
}
```

- [ ] **Step 2: Write the set file**

Create `src/Rector/config/conventions.php`:

```php
<?php

declare(strict_types=1);

use Gamache\Rector\InjectRepositoryInsteadOfGetRepositoryRector;
use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(InjectRepositoryInsteadOfGetRepositoryRector::class);
    $rectorConfig->rule(SortCallLikeNamedArgsRector::class);
    $rectorConfig->rule(SortAttributeNamedArgsRector::class);
};
```

- [ ] **Step 3: Write the test config that consumes the set the way a real consumer would**

Create `tests/Rector/GamacheConventionsSet/config/configured_set.php`:

```php
<?php

declare(strict_types=1);

use Gamache\Rector\GamacheSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([GamacheSetList::CONVENTIONS]);
```

- [ ] **Step 4: Write the fixture**

Create `tests/Rector/GamacheConventionsSet/Fixture/sorts_named_args.php.inc`:

```
<?php

namespace Gamache\Tests\Rector\GamacheConventionsSet\Fixture;

function run(?int $foo = null, ?int $bar = null): void
{
}

run(bar: 2, foo: 1);

?>
-----
<?php

namespace Gamache\Tests\Rector\GamacheConventionsSet\Fixture;

function run(?int $foo = null, ?int $bar = null): void
{
}

run(foo: 1, bar: 2);

?>
```

- [ ] **Step 5: Write the test**

Create `tests/Rector/GamacheConventionsSet/GamacheConventionsSetTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\Tests\Rector\GamacheConventionsSet;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class GamacheConventionsSetTest extends AbstractRectorTestCase
{
    #[DataProvider('provideData')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public static function provideData(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/config/configured_set.php';
    }
}
```

- [ ] **Step 6: Run the test**

Run: `vendor/bin/phpunit tests/Rector/GamacheConventionsSet/GamacheConventionsSetTest.php`
Expected: PASS (1 test) — the fixture's `run(bar: 2, foo: 1)` is rewritten to `run(foo: 1, bar: 2)`, proving the set loads via `withSets` and the sorter is active.

- [ ] **Step 7: Commit**

```bash
git add src/Rector/GamacheSetList.php src/Rector/config/conventions.php tests/Rector/GamacheConventionsSet/
git commit -m "feat(rector): GamacheSetList::CONVENTIONS set (custom rule + named-arg sorters)"
```

---

### Task 3: Twig-CS-Fixer `GamacheStandard`

**Goal:** A `GamacheStandard` implementing `StandardInterface` that returns all four gamache Twig rules, consumable via `Ruleset::addStandard()`.

**Files:**
- Create: `src/TwigCsFixer/GamacheStandard.php`
- Test: `tests/TwigCsFixer/GamacheStandardTest.php`

**Acceptance Criteria:**
- [ ] `(new GamacheStandard())->getRules()` returns exactly one instance each of `CsrfTokenValueRule`, `IncludeOnlyRule`, `InlineSvgRule`, `TranslationKeyRule`.

**Verify:** `vendor/bin/phpunit tests/TwigCsFixer/GamacheStandardTest.php` → OK (1 test)

**Steps:**

- [ ] **Step 1: Write the failing test**

Create `tests/TwigCsFixer/GamacheStandardTest.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\Tests\TwigCsFixer;

use Gamache\TwigCsFixer\CsrfTokenValueRule;
use Gamache\TwigCsFixer\GamacheStandard;
use Gamache\TwigCsFixer\IncludeOnlyRule;
use Gamache\TwigCsFixer\InlineSvgRule;
use Gamache\TwigCsFixer\TranslationKeyRule;
use PHPUnit\Framework\TestCase;

final class GamacheStandardTest extends TestCase
{
    public function test_standard_provides_all_gamache_twig_rules(): void
    {
        $rules = (new GamacheStandard())->getRules();

        $classes = array_map(static fn (object $r): string => $r::class, $rules);

        self::assertEqualsCanonicalizing([
            CsrfTokenValueRule::class,
            IncludeOnlyRule::class,
            InlineSvgRule::class,
            TranslationKeyRule::class,
        ], $classes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/TwigCsFixer/GamacheStandardTest.php`
Expected: FAIL — `Gamache\TwigCsFixer\GamacheStandard` not found.

- [ ] **Step 3: Write the implementation**

Create `src/TwigCsFixer/GamacheStandard.php`:

```php
<?php

declare(strict_types=1);

namespace Gamache\TwigCsFixer;

use TwigCsFixer\Standard\StandardInterface;

/**
 * Aggregate of every gamache Twig-CS-Fixer rule. Reference via
 * `$ruleset->addStandard(new GamacheStandard())` so new gamache rules arrive
 * automatically on `composer update` without editing the config.
 */
final class GamacheStandard implements StandardInterface
{
    public function getRules(): array
    {
        return [
            new CsrfTokenValueRule(),
            new IncludeOnlyRule(),
            new InlineSvgRule(),
            new TranslationKeyRule(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/TwigCsFixer/GamacheStandardTest.php`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add src/TwigCsFixer/GamacheStandard.php tests/TwigCsFixer/GamacheStandardTest.php
git commit -m "feat(twig-cs-fixer): GamacheStandard bundling all twig rules"
```

---

### Task 4: Documentation rewrite

**Goal:** Update README and per-tool docs to show aggregate usage and state that referencing the aggregate (not listing rules) is what makes upgrades automatic.

**Files:**
- Modify: `README.md` (sections "PHP-CS-Fixer fixers", "Twig-CS-Fixer rules", "Rector rule")
- Modify: `docs/php-cs-fixer.md`
- Modify: `docs/rector.md`
- Modify: `docs/twig-cs-fixer.md`

**Acceptance Criteria:**
- [ ] Each tool's section shows the aggregate usage (`Fixers` / `GamacheSetList::CONVENTIONS` / `GamacheStandard`).
- [ ] Each section states new rules arrive automatically on `composer update` when referencing the aggregate.
- [ ] No stale "register each rule by hand" snippet remains as the primary example.

**Verify:** `grep -rl "GamacheSetList\|GamacheStandard\|Fixers::rules\|new Fixers" README.md docs/` lists all four files; manual read confirms snippets compile against the new classes.

**Steps:**

- [ ] **Step 1: Replace the README "PHP-CS-Fixer fixers" snippet**

Replace the existing `registerCustomFixers([...])` example with:

```php
use Gamache\PhpCsFixer\Fixers;

return (new PhpCsFixer\Config())
    ->registerCustomFixers(new Fixers())
    ->setRules([
        '@Symfony' => true,
        ...Fixers::rules(),   // gamache conventions; new ones arrive on `composer update`
    ]);
```

Add one sentence: referencing `Fixers` instead of listing rules is what makes upgrades automatic. Note `ordered_attributes` (alphabetical attribute order) is included.

- [ ] **Step 2: Replace the README "Twig-CS-Fixer rules" snippet**

```php
use Gamache\TwigCsFixer\GamacheStandard;
use TwigCsFixer\Config\Config;
use TwigCsFixer\Ruleset\Ruleset;

$ruleset = new Ruleset();
$ruleset->addStandard(new GamacheStandard());

return (new Config())->setRuleset($ruleset);
```

Add the same auto-upgrade sentence.

- [ ] **Step 3: Replace the README "Rector rule" snippet**

```php
use Gamache\Rector\GamacheSetList;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSets([GamacheSetList::CONVENTIONS]);
```

State that `CONVENTIONS` bundles `InjectRepositoryInsteadOfGetRepositoryRector` plus the named-argument sorters (`SortCallLikeNamedArgsRector`, `SortAttributeNamedArgsRector`), and that it grows on upgrade. Note `InjectRepository…` rewrites constructors.

- [ ] **Step 4: Update `docs/php-cs-fixer.md`**

Add a top section showing the `Fixers` aggregate as the recommended setup; keep the per-fixer reference (options tables) below it. State the curated built-in `ordered_attributes` is part of `Fixers::rules()`.

- [ ] **Step 5: Update `docs/rector.md`**

Add a top section showing `->withSets([GamacheSetList::CONVENTIONS])`; document that the set includes the two built-in named-arg sorters alongside the custom rule. Keep the existing `InjectRepository…` detail below.

- [ ] **Step 6: Update `docs/twig-cs-fixer.md`**

Add a top section showing `addStandard(new GamacheStandard())`; keep the per-rule reference below.

- [ ] **Step 7: Commit**

```bash
git add README.md docs/php-cs-fixer.md docs/rector.md docs/twig-cs-fixer.md
git commit -m "docs: show per-tool aggregates and auto-upgrade behavior"
```

---

## Final verification

- [ ] Run the full suite: `vendor/bin/phpunit` → all green.
- [ ] Run static analysis: `vendor/bin/phpstan analyse --no-progress` → no errors.
- [ ] Open a PR against `main` (branch `feat/tool-presets`).
