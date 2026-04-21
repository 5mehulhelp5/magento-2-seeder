# Commerce Faker Provider Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Bring `@faker-js/faker`'s commerce module to this repo by adding a PHP `Faker\Provider` with curated adjective / material / product / department wordlists, wire it into `FakerFactory`, and swap the lorem-ipsum fallbacks in `ProductDataGenerator` and `CategoryDataGenerator` for the new methods.

**Architecture:** One new `Faker\Provider\Base` subclass backed by a locale-mapped wordlist class. A `CommerceProviderFactory` resolves the locale with silent English fallback. `FakerFactory` composes the factory and registers the provider on every `Generator` it creates — so all existing and future callers of the seeder's Faker pick up `productName()` / `productDepartment()` automatically.

**Tech Stack:** PHP 8.1+, Magento 2 module, `fakerphp/faker` ^1.23 (already installed), PHPUnit 10, Mockery 1.6 (already installed).

**Design doc:** `docs/plans/2026-04-21-commerce-faker-design.md`

**Conventions to follow:**
- Test methods are `snake_case` and classes are `final` (from `~/.claude/CLAUDE.md`).
- Namespace root is `RunAsRoot\Seeder\`.
- Company-module convention, not personal.
- PSR-12 + Magento coding standard (enforced by `composer phpcs`).
- Never skip hooks; run `composer check` before each commit.
- `declare(strict_types=1);` at the top of every new PHP file.

**Commit convention (from recent `git log`):** `type(scope): summary` — e.g. `feat(faker-commerce): ...`, `test(faker-commerce): ...`, `docs(readme): ...`.

**Attribution pin:** All wordlist ports are derived from this upstream commit:
`https://github.com/faker-js/faker/tree/next/src/locales/en/commerce`
The implementer records the exact commit hash (copy from the FakerJS repo's tree page at port time) in the `NOTICE` file created in Task 1 and in the header comment of `src/Faker/Provider/Data/Commerce/EnUs.php` (Task 2).

---

## Task 1: Attribution — `NOTICE` file

**Files:**
- Create: `NOTICE`

**Step 1: Write the `NOTICE` file**

Create `NOTICE` at the repo root with:

```
RunAsRoot Magento 2 Seeder
Copyright (c) RunAsRoot GmbH

This product includes software developed by third parties under the MIT license:

@faker-js/faker (commerce module wordlists)
  https://github.com/faker-js/faker
  Wordlists under src/Faker/Provider/Data/Commerce/ were ported verbatim
  from the FakerJS `en` commerce locale at upstream commit:
    <COMMIT-HASH>
  Copyright (c) 2022 Faker
  Licensed under the MIT License.
```

Replace `<COMMIT-HASH>` with the current HEAD commit of the `next` branch
at `https://github.com/faker-js/faker` at port time.

**Step 2: Verify**

Run: `composer check`
Expected: All pass. `NOTICE` is plain text, no lint impact.

**Step 3: Commit**

```bash
git add NOTICE
git commit -m "docs(notice): attribute upstream @faker-js/faker wordlists"
```

---

## Task 2: Port `en_US` wordlists — `EnUs` data class

**Files:**
- Create: `src/Faker/Provider/Data/Commerce/EnUs.php`

**Step 1: Fetch the upstream source**

Open each of these FakerJS source files on the `next` branch and copy the
string arrays verbatim (drop the TypeScript `export default [...]` wrapper):

- `src/locales/en/commerce/product_name/adjective.ts` → `ADJECTIVES`
- `src/locales/en/commerce/product_name/material.ts` → `MATERIALS`
- `src/locales/en/commerce/product_name/product.ts` → `PRODUCTS`
- `src/locales/en/commerce/department.ts` → `DEPARTMENTS`

All four files are plain string arrays. Copy them unchanged — no
reordering, no deduplication, no cherry-picking. The whole point of
verbatim porting is that we don't introduce our own quality-drift bugs.

**Step 2: Create the data class**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider\Data\Commerce;

/**
 * Commerce wordlists ported verbatim from @faker-js/faker (MIT).
 * See NOTICE at repo root for attribution + upstream commit hash.
 *
 * Refresh instructions: src/Faker/Provider/Data/Commerce/README.md
 */
final class EnUs
{
    /** @return list<string> */
    public static function adjectives(): array
    {
        return self::ADJECTIVES;
    }

    /** @return list<string> */
    public static function materials(): array
    {
        return self::MATERIALS;
    }

    /** @return list<string> */
    public static function products(): array
    {
        return self::PRODUCTS;
    }

    /** @return list<string> */
    public static function departments(): array
    {
        return self::DEPARTMENTS;
    }

    /** @var list<string> */
    private const ADJECTIVES = [
        // PASTE FROM: src/locales/en/commerce/product_name/adjective.ts
    ];

    /** @var list<string> */
    private const MATERIALS = [
        // PASTE FROM: src/locales/en/commerce/product_name/material.ts
    ];

    /** @var list<string> */
    private const PRODUCTS = [
        // PASTE FROM: src/locales/en/commerce/product_name/product.ts
    ];

    /** @var list<string> */
    private const DEPARTMENTS = [
        // PASTE FROM: src/locales/en/commerce/department.ts
    ];
}
```

**Step 3: Sanity-check the port**

Run this one-liner to confirm all four arrays are populated and non-trivial:

```bash
php -r 'require "vendor/autoload.php"; use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs; var_dump(count(EnUs::adjectives()), count(EnUs::materials()), count(EnUs::products()), count(EnUs::departments()));'
```

Expected: Each count is between 15 and 200 (FakerJS's `en` lists are
~50 adj / ~50 mat / ~50 prod / ~25 dept at time of writing). If any
count is 0, the paste went wrong — redo.

**Step 4: Verify no regressions**

Run: `composer check`
Expected: phpcs, phpstan, phpunit all pass.

**Step 5: Commit**

```bash
git add src/Faker/Provider/Data/Commerce/EnUs.php
git commit -m "feat(faker-commerce): port en_US wordlists from @faker-js/faker"
```

---

## Task 3: Locale contract — `CommerceLocaleInterface`

**Files:**
- Create: `src/Faker/Provider/CommerceLocaleInterface.php`

**Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

/**
 * Source of locale-specific commerce wordlists consumed by CommerceProvider.
 * Each method returns a non-empty list of strings.
 */
interface CommerceLocaleInterface
{
    /** @return non-empty-list<string> */
    public function adjectives(): array;

    /** @return non-empty-list<string> */
    public function materials(): array;

    /** @return non-empty-list<string> */
    public function products(): array;

    /** @return non-empty-list<string> */
    public function departments(): array;
}
```

**Step 2: Make `EnUs` implement the interface**

Edit `src/Faker/Provider/Data/Commerce/EnUs.php`:
- Add `use RunAsRoot\Seeder\Faker\Provider\CommerceLocaleInterface;`
- Change `final class EnUs` to `final class EnUs implements CommerceLocaleInterface`
- Change every `public static function ...(): array` to `public function ...(): array` (drop `static`).

Leave the private const arrays as-is.

**Step 3: Verify**

Run: `composer check`
Expected: All pass.

**Step 4: Commit**

```bash
git add src/Faker/Provider/CommerceLocaleInterface.php src/Faker/Provider/Data/Commerce/EnUs.php
git commit -m "feat(faker-commerce): add locale contract + make EnUs implement it"
```

---

## Task 4: `CommerceProvider` — test first

**Files:**
- Create: `Test/Unit/Faker/Provider/CommerceProviderTest.php`
- Create: `src/Faker/Provider/CommerceProvider.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\CommerceLocaleInterface;
use RunAsRoot\Seeder\Faker\Provider\CommerceProvider;
use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\TestCase;

final class CommerceProviderTest extends TestCase
{
    public function test_product_adjective_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productAdjective(), $locale->adjectives());
    }

    public function test_product_material_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productMaterial(), $locale->materials());
    }

    public function test_product_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->product(), $locale->products());
    }

    public function test_product_department_returns_entry_from_locale_list(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $this->assertContains($faker->productDepartment(), $locale->departments());
    }

    public function test_product_name_returns_three_word_string_from_component_lists(): void
    {
        $locale = new EnUs();
        $faker = $this->fakerWithCommerce($locale);

        $name = $faker->productName();

        $words = explode(' ', $name);
        $this->assertCount(3, $words, "expected 3-word name, got '$name'");
        $this->assertContains($words[0], $locale->adjectives());
        $this->assertContains($words[1], $locale->materials());
        $this->assertContains($words[2], $locale->products());
    }

    public function test_seeded_generator_produces_deterministic_product_name(): void
    {
        $locale = new EnUs();

        $faker1 = $this->fakerWithCommerce($locale);
        $faker1->seed(1234);
        $name1 = $faker1->productName();

        $faker2 = $this->fakerWithCommerce($locale);
        $faker2->seed(1234);
        $name2 = $faker2->productName();

        $this->assertSame($name1, $name2);
    }

    private function fakerWithCommerce(CommerceLocaleInterface $locale): Generator
    {
        $faker = Factory::create('en_US');
        $faker->addProvider(new CommerceProvider($faker, $locale));
        return $faker;
    }
}
```

**Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit Test/Unit/Faker/Provider/CommerceProviderTest.php`
Expected: FAIL with "Class RunAsRoot\Seeder\Faker\Provider\CommerceProvider not found".

**Step 3: Implement `CommerceProvider`**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

use Faker\Generator;
use Faker\Provider\Base;

final class CommerceProvider extends Base
{
    public function __construct(Generator $generator, private readonly CommerceLocaleInterface $locale)
    {
        parent::__construct($generator);
    }

    public function productAdjective(): string
    {
        return static::randomElement($this->locale->adjectives());
    }

    public function productMaterial(): string
    {
        return static::randomElement($this->locale->materials());
    }

    public function product(): string
    {
        return static::randomElement($this->locale->products());
    }

    public function productDepartment(): string
    {
        return static::randomElement($this->locale->departments());
    }

    public function productName(): string
    {
        return $this->productAdjective() . ' ' . $this->productMaterial() . ' ' . $this->product();
    }
}
```

**Step 4: Run tests — expect pass**

Run: `vendor/bin/phpunit Test/Unit/Faker/Provider/CommerceProviderTest.php`
Expected: 6/6 passing.

**Step 5: Verify full suite**

Run: `composer check`
Expected: All pass.

**Step 6: Commit**

```bash
git add src/Faker/Provider/CommerceProvider.php Test/Unit/Faker/Provider/CommerceProviderTest.php
git commit -m "feat(faker-commerce): add CommerceProvider with product* + department methods"
```

---

## Task 5: `CommerceProviderFactory` — test first

**Files:**
- Create: `Test/Unit/Faker/Provider/CommerceProviderFactoryTest.php`
- Create: `src/Faker/Provider/CommerceProviderFactory.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\CommerceProvider;
use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CommerceProviderFactoryTest extends TestCase
{
    public function test_create_with_en_us_returns_provider_backed_by_english_wordlists(): void
    {
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('en_US');

        $provider = $factory->create('en_US', $faker);

        $this->assertInstanceOf(CommerceProvider::class, $provider);
        $faker->addProvider($provider);
        $this->assertContains($faker->productAdjective(), (new EnUs())->adjectives());
    }

    public function test_create_with_unknown_locale_falls_back_to_en_us_wordlists_silently(): void
    {
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('xx_YY');

        $provider = $factory->create('xx_YY', $faker);

        $this->assertInstanceOf(CommerceProvider::class, $provider);
        $faker->addProvider($provider);
        // Silent fallback: no exception, no warning, English wordlists used.
        $this->assertContains($faker->productDepartment(), (new EnUs())->departments());
    }

    public function test_create_with_de_de_currently_falls_back_to_en_us(): void
    {
        // v1 scope: only en_US is mapped. Fallback is explicit and documented.
        $factory = new CommerceProviderFactory();
        $faker = Factory::create('de_DE');

        $provider = $factory->create('de_DE', $faker);

        $faker->addProvider($provider);
        $this->assertContains($faker->productAdjective(), (new EnUs())->adjectives());
    }
}
```

**Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit Test/Unit/Faker/Provider/CommerceProviderFactoryTest.php`
Expected: FAIL with "Class RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory not found".

**Step 3: Implement `CommerceProviderFactory`**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Faker\Provider;

use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
use Faker\Generator;

final class CommerceProviderFactory
{
    /** @var array<string, class-string<CommerceLocaleInterface>> */
    private const LOCALE_MAP = [
        'en_US' => EnUs::class,
    ];

    public function create(string $locale, Generator $generator): CommerceProvider
    {
        $localeClass = self::LOCALE_MAP[$locale] ?? EnUs::class;

        return new CommerceProvider($generator, new $localeClass());
    }
}
```

**Step 4: Run tests — expect pass**

Run: `vendor/bin/phpunit Test/Unit/Faker/Provider/CommerceProviderFactoryTest.php`
Expected: 3/3 passing.

**Step 5: Verify full suite**

Run: `composer check`
Expected: All pass.

**Step 6: Commit**

```bash
git add src/Faker/Provider/CommerceProviderFactory.php Test/Unit/Faker/Provider/CommerceProviderFactoryTest.php
git commit -m "feat(faker-commerce): add factory with en_US mapping + silent fallback"
```

---

## Task 6: Wire into `FakerFactory`

**Files:**
- Modify: `src/Service/FakerFactory.php`
- Modify: `Test/Unit/Service/FakerFactoryTest.php`

**Step 1: Add a failing test for commerce method availability**

Edit `Test/Unit/Service/FakerFactoryTest.php`. Add this test at the bottom of the class:

```php
public function test_created_faker_exposes_commerce_methods(): void
{
    $factory = new FakerFactory(new CommerceProviderFactory());
    $faker = $factory->create('en_US');

    $name = $faker->productName();
    $this->assertNotEmpty($name);
    $this->assertCount(3, explode(' ', $name));

    $department = $faker->productDepartment();
    $this->assertNotEmpty($department);
}
```

Also add the import: `use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;`

**Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit Test/Unit/Service/FakerFactoryTest.php::test_created_faker_exposes_commerce_methods`
Expected: FAIL with "Too few arguments to function FakerFactory::__construct" or "Call to undefined method Generator::productName()".

**Step 3: Update `FakerFactory`**

Edit `src/Service/FakerFactory.php`. Replace the whole body:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Service;

use RunAsRoot\Seeder\Faker\Provider\CommerceProviderFactory;
use Faker\Factory;
use Faker\Generator;

class FakerFactory
{
    public function __construct(private readonly CommerceProviderFactory $commerceProviderFactory)
    {
    }

    public function create(string $locale = 'en_US', ?int $seed = null): Generator
    {
        $faker = Factory::create($locale);
        $faker->addProvider($this->commerceProviderFactory->create($locale, $faker));

        if ($seed !== null) {
            $faker->seed($seed);
        }

        return $faker;
    }
}
```

**Step 4: Update existing `FakerFactoryTest` constructor calls**

Every `new FakerFactory()` in the test file now needs the factory arg.
Do a find-replace in `Test/Unit/Service/FakerFactoryTest.php`:
- `new FakerFactory()` → `new FakerFactory(new CommerceProviderFactory())`

(Four call sites: `test_creates_faker_with_default_locale`,
`test_creates_faker_with_custom_locale`,
`test_creates_deterministic_faker_with_seed`,
`test_creates_random_faker_without_seed`.)

**Step 5: Run tests — expect pass**

Run: `vendor/bin/phpunit Test/Unit/Service/FakerFactoryTest.php`
Expected: 5/5 passing.

**Step 6: Run the full suite to catch collateral breakage**

Run: `composer check`
Expected: All pass. If anything else instantiates `FakerFactory` without args, it'll surface here.

If other tests fail due to the new constructor argument, fix each callsite the same way (`new FakerFactory(new CommerceProviderFactory())`). Common candidates to check: `Test/Unit/Service/GenerateRunnerTest.php`, `Test/Unit/SeederTest.php`, `Test/Unit/SeedBuilderTest.php`, `Test/Integration/SeederFacadeSmokeTest.php`. Magento DI handles production wiring — no `di.xml` changes needed.

**Step 7: Commit**

```bash
git add src/Service/FakerFactory.php Test/Unit/Service/FakerFactoryTest.php
# plus any other test files you had to update
git commit -m "feat(faker-commerce): wire CommerceProvider into FakerFactory"
```

---

## Task 7: Swap `ProductDataGenerator` to `productName()`

**Files:**
- Modify: `src/DataGenerator/ProductDataGenerator.php:46`

**Step 1: Confirm existing tests still pass before the swap**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/ProductDataGeneratorTest.php`
Expected: All pass (baseline).

**Step 2: Swap the call**

Edit `src/DataGenerator/ProductDataGenerator.php`. On line 46, replace:

```php
$name = ucwords($faker->words($faker->numberBetween(2, 4), true));
```

with:

```php
$name = $faker->productName();
```

**Step 3: Run the generator tests**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/ProductDataGeneratorTest.php`

The existing tests construct a bare `Faker\Factory::create('en_US')`
without registering `CommerceProvider`. Calling `productName()` on that
raw faker will throw "undefined method". Fix by registering the provider
in the same file — find-replace this pattern throughout:

```php
// before
$faker = Factory::create('en_US');
```

with:

```php
// after
$faker = Factory::create('en_US');
$faker->addProvider(new CommerceProvider($faker, new EnUs()));
```

Add imports at the top of `ProductDataGeneratorTest.php`:

```php
use RunAsRoot\Seeder\Faker\Provider\CommerceProvider;
use RunAsRoot\Seeder\Faker\Provider\Data\Commerce\EnUs;
```

Expected after the test-file update: all existing tests pass (they only
assert shape, not name content).

**Step 4: Verify no regressions**

Run: `composer check`
Expected: All pass.

**Step 5: Commit**

```bash
git add src/DataGenerator/ProductDataGenerator.php Test/Unit/DataGenerator/ProductDataGeneratorTest.php
git commit -m "feat(faker-commerce): use productName() in ProductDataGenerator"
```

---

## Task 8: Swap `CategoryDataGenerator` to `productDepartment()`

**Files:**
- Modify: `src/DataGenerator/CategoryDataGenerator.php`
- Modify: `Test/Unit/DataGenerator/CategoryDataGeneratorTest.php`

**Step 1: Confirm baseline**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/CategoryDataGeneratorTest.php`
Expected: All pass.

**Step 2: Update the generator**

Edit `src/DataGenerator/CategoryDataGenerator.php`:

1. Delete the `COMMERCE_CATEGORIES` const block (lines 13–18 roughly).
2. Replace the `$name = ...` line (line 32):

   ```php
   // before
   $name = $faker->randomElement(self::COMMERCE_CATEGORIES) . ' ' . $faker->word();
   // after
   $name = $faker->productDepartment();
   ```

3. Drop the `ucwords()` wrapping around `$name` in the return array (line 42 —
   `productDepartment()` returns already-cased strings like "Electronics"):

   ```php
   // before
   'name' => ucwords($name),
   // after
   'name' => $name,
   ```

**Step 3: Update the test file to register the provider**

Edit `Test/Unit/DataGenerator/CategoryDataGeneratorTest.php`. Same pattern
as Task 7 Step 3 — every `Factory::create('en_US')` needs the commerce
provider added:

```php
$faker = Factory::create('en_US');
$faker->addProvider(new CommerceProvider($faker, new EnUs()));
```

Add the imports.

**Step 4: Run the generator tests**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/CategoryDataGeneratorTest.php`
Expected: All pass.

**Step 5: Verify full suite**

Run: `composer check`
Expected: All pass.

**Step 6: Commit**

```bash
git add src/DataGenerator/CategoryDataGenerator.php Test/Unit/DataGenerator/CategoryDataGeneratorTest.php
git commit -m "feat(faker-commerce): use productDepartment() in CategoryDataGenerator"
```

---

## Task 9: Data refresh docs

**Files:**
- Create: `src/Faker/Provider/Data/Commerce/README.md`

**Step 1: Write the refresh guide**

```markdown
# Commerce Wordlist Refresh

Wordlists in this directory are ported verbatim from `@faker-js/faker`.
FakerJS updates these lists rarely (2–3 entries/year). Refresh manually
when output looks stale.

## Current port source

See `NOTICE` at repo root for the exact upstream commit hash.

## How to refresh (en_US)

Upstream: https://github.com/faker-js/faker/tree/next/src/locales/en/commerce

1. Open each of these four files on the FakerJS `next` branch:
   - `product_name/adjective.ts`
   - `product_name/material.ts`
   - `product_name/product.ts`
   - `department.ts`
2. Copy the string array out of each file (drop the `export default` wrapper).
3. Paste into the matching `private const` block in `EnUs.php`.
4. Update the commit hash in `NOTICE` to the current upstream HEAD.
5. Run `composer check`.
6. Commit: `chore(faker-commerce): refresh en_US wordlists from upstream <hash>`.

## Adding a new locale

1. Create `src/Faker/Provider/Data/Commerce/<LocaleName>.php` — copy
   `EnUs.php` as a template, implement `CommerceLocaleInterface`, paste
   the locale's wordlists from `@faker-js/faker/src/locales/<xx>/commerce`.
2. Register the class in `CommerceProviderFactory::LOCALE_MAP` keyed by
   the Faker locale string (e.g. `'de_DE' => DeDe::class`).
3. Add a test in `CommerceProviderFactoryTest` asserting that
   `create('<xx_YY>', $faker)` returns a provider backed by the new class's
   wordlists.
4. Update `NOTICE` if the source commit differs.
```

**Step 2: Verify**

Run: `composer check`
Expected: All pass (markdown, no lint impact).

**Step 3: Commit**

```bash
git add src/Faker/Provider/Data/Commerce/README.md
git commit -m "docs(faker-commerce): add wordlist refresh + locale-extension guide"
```

---

## Task 10: Surface the feature in top-level `README.md`

**Files:**
- Modify: `README.md`

**Step 1: Add a section after "Data Generation with Faker"**

Find the `## Data Generation with Faker` heading. After the
`### Count-Based Seeder Files` subsection (and before `## Product Reviews`),
insert:

````markdown
### Commerce-quality fake data

Faker's default `words()` / `sentence()` helpers produce lorem-ipsum —
fine for `description` fields, bad for product *names*. This module
registers a `CommerceProvider` on every `Faker\Generator` it hands out,
mirroring the `commerce` module from [@faker-js/faker][fjs] (MIT).

Methods available on `$faker` in any custom seeder / data generator:

| Method | Example |
|--------|---------|
| `$faker->productName()` | `Handcrafted Rubber Pizza` |
| `$faker->productAdjective()` | `Handcrafted` |
| `$faker->productMaterial()` | `Rubber` |
| `$faker->product()` | `Pizza` |
| `$faker->productDepartment()` | `Electronics` |

Used internally by `ProductDataGenerator` (product names) and
`CategoryDataGenerator` (category names). Locale is `en_US` only in v1;
other locales silently fall back to English wordlists. See
`src/Faker/Provider/Data/Commerce/README.md` for refresh + locale-extension
instructions.

[fjs]: https://github.com/faker-js/faker
````

**Step 2: Verify**

Run: `composer check`
Expected: All pass.

**Step 3: Commit**

```bash
git add README.md
git commit -m "docs(readme): document commerce faker methods on \$faker"
```

---

## Done criteria

- `vendor/bin/phpunit` fully green.
- `composer check` fully green.
- `$faker->productName()` returns three-word commerce-style names from the
  ported `en_US` wordlists for any `Generator` produced by `FakerFactory`.
- `$faker->productDepartment()` returns non-empty English department names.
- `bin/magento db:seed --generate=product:10 --seed=42 --fresh` (integration,
  manual) produces products with names like "Handcrafted Rubber Pizza" instead
  of "Dolor Sit Amet".
- `NOTICE` attributes the upstream source + commit hash.
- README advertises the new `$faker->product*()` methods.

**Release is NOT part of this plan.** Seeder releases are manual
(CHANGELOG bump + tag + gh release) per the repo's release flow; do that
in a follow-up once the user signs off.
