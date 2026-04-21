# Commerce Faker Provider — Design

**Date:** 2026-04-21
**Status:** Approved, ready for implementation plan.

## Problem

`fakerphp/faker` lacks a commerce module. FakerJS ships one: curated
adjective / material / product / department wordlists that produce names
like "Handcrafted Rubber Pizza" and departments like "Electronics",
"Jewelery", "Garden".

In this seeder the gap shows up in two places:

- `ProductDataGenerator::generate()` uses `$faker->words(2-4, true)` for
  product names → output reads like "Dolor Sit Amet".
- `CategoryDataGenerator::generate()` carries a hardcoded 20-entry English
  commerce list and tacks a random lorem word onto each name
  ("Electronics labore").

Both paths produce obviously-fake demo data that hurts the "show a realistic
store to a merchant" use case the seeder is built for.

## Goal

Bring FakerJS's commerce module quality to this seeder by introducing a
PHP `Faker\Provider` that ports the FakerJS English wordlists verbatim,
then wire it into the seeder's `FakerFactory` so every consumer of the
`Faker\Generator` gets commerce methods for free.

Non-goals (explicit):

- Multi-locale support beyond `en_US` fallback.
- `productDescription()`, `color()`, `price()`, `isbn()` — not used today.
- A refresh build step. Wordlists change maybe 2–3 entries/year upstream;
  manual refresh is cheaper than automation here.

## Architecture

Pure `Faker\Provider\Base` subclass. No seeder-internal dependencies (no
`GeneratedDataRegistry`, no Magento types). Decoupling keeps the provider
trivially extractable into a standalone package later if demand appears.

**Public surface (mirrors FakerJS commerce):**

- `productName(): string` — "Handcrafted Rubber Pizza" (adjective + material + product).
- `productAdjective(): string`
- `productMaterial(): string`
- `productDepartment(): string` — "Electronics", "Garden", "Jewelery", etc.
- `product(): string` — single product noun (used by `productName()`, exposed at zero extra cost).

**Wordlist storage:**

Wordlists live as `private const` arrays on one data class per locale:

```
src/Faker/Provider/Data/Commerce/EnUs.php
```

The provider consumes them via a small `CommerceLocaleInterface` so adding
`de_DE` later is one class + one map entry, no provider changes.

**Locale resolution:**

A `CommerceProviderFactory` maps the requested locale string (e.g.
`de_DE`) to a `CommerceLocaleInterface` implementation, falling back to
`EnUs` for any unmapped locale. Silent fallback — Faker's own providers
behave the same way. No warning spam.

**Namespace layout:**

```
src/Faker/Provider/CommerceProvider.php
src/Faker/Provider/CommerceProviderFactory.php
src/Faker/Provider/CommerceLocaleInterface.php
src/Faker/Provider/Data/Commerce/EnUs.php
```

## Integration Points

Two touchpoints in existing seeder code, both pure additions.

**`src/Service/FakerFactory.php`** — one new line after `Factory::create()`:

```php
$faker->addProvider($this->commerceProviderFactory->create($locale, $faker));
```

`FakerFactory` gains a constructor-injected `CommerceProviderFactory`. All
callers — `DataGenerator` classes, class-based `Seeder` subclasses, user
code reaching into `Generator` — get `$faker->productName()` automatically.
No opt-in flag; additive, no BC risk.

**`src/DataGenerator/ProductDataGenerator.php`** — swap one line:

```php
// before
$name = ucwords($faker->words($faker->numberBetween(2, 4), true));
// after
$name = $faker->productName();
```

**`src/DataGenerator/CategoryDataGenerator.php`** — replace
`COMMERCE_CATEGORIES` + lorem-suffix logic:

```php
// before
$name = $faker->randomElement(self::COMMERCE_CATEGORIES) . ' ' . $faker->word();
// after
$name = $faker->productDepartment();
```

The 20-entry hardcoded `COMMERCE_CATEGORIES` constant is deleted.
`productDepartment()` supersedes it — wider variety, no lorem suffix.

No `di.xml` changes required. Magento auto-wires the new constructor
dependency on `FakerFactory`.

## Data Sourcing & Refresh

**One-time manual port** from `@faker-js/faker`'s `src/locales/en/commerce`:

- `product_name/adjective.ts` → `EnUs::ADJECTIVES` (~50 entries)
- `product_name/material.ts` → `EnUs::MATERIALS` (~50 entries)
- `product_name/product.ts`  → `EnUs::PRODUCTS` (~50 entries)
- `department.ts`            → `EnUs::DEPARTMENTS` (~25 entries)

Combinations: ~50 × 50 × 50 = ~125k unique product-name strings. Plenty
for dev-scale seeds (`--generate=product:5000` has no collision risk).

**No refresh script.** Manual refresh docs live at
`src/Faker/Provider/Data/Commerce/README.md`:

- Upstream URL: `https://github.com/faker-js/faker/tree/next/src/locales/en/commerce`
- Commit hash the port was based on (recorded on port; bumped on refresh).
- Three `curl` one-liners + hand-paste instructions.

5-minute manual refresh when someone complains the lists are stale.
Cheaper than a build step that runs once a year.

## Licensing

Wordlists are derived from `@faker-js/faker` (MIT). Attribution:

- Header comment on each locale data class: *"Wordlists derived from
  @faker-js/faker (MIT). See NOTICE."*
- New `NOTICE` file at repo root with the full MIT copyright text + the
  upstream commit hash the port was based on.
- `composer.json` already declares MIT; no license conflict.

## Testing

**New unit test — `Test/Unit/Faker/Provider/CommerceProviderTest.php`** (~6 cases):

- `product_name_returns_three_word_string`
- `product_name_words_come_from_expected_lists` (membership assertions)
- `product_adjective_returns_non_empty_string_from_list`
- `product_material_returns_non_empty_string_from_list`
- `product_department_returns_non_empty_string_from_list`
- `seeded_generator_produces_deterministic_output`

**New unit test — `Test/Unit/Faker/Provider/CommerceProviderFactoryTest.php`** (~2 cases):

- `create_with_en_us_returns_commerce_provider`
- `create_with_unknown_locale_falls_back_to_en_us_wordlists`

**Existing generator tests:** `ProductDataGeneratorTest` and
`CategoryDataGeneratorTest` likely assert shape only (`name` is a
non-empty string). Verify on port; adjust only if any case pinned on
2–4 word counts or on `COMMERCE_CATEGORIES` membership.

**What we're NOT testing:**

- Wordlist content correctness (verbatim upstream port; FakerJS owns quality).
- Statistical distribution (Faker's own concern; we just wrap its `randomElement`).

**No integration tests.** Zero DB paths, zero Magento bootstrap —
pure PHP + wordlists.

## Out of Scope / Follow-ups

- `de_DE` locale: add when a German demo store asks for it. Pattern is
  already in place — one data class + one factory map entry.
- Extraction to `runasroot/faker-commerce-provider` standalone package:
  promote in a minor seeder release with composer `replace` note if a
  second consumer appears.
- `productDescription()`, `color()`, `price()`, `isbn()`: add on demand.
  None are needed by current generators.

## Risks

- **Wordlist staleness** — low risk, low blast. Manual refresh covers it.
- **Locale fallback silently returns English for `de_DE`** — acceptable
  for v1 per explicit scope decision. Documented in README.
- **FakerJS changes licensing** — extremely unlikely (MIT is perpetual on
  already-published commits). Attribution is pinned to a specific upstream
  commit hash, so future upstream changes don't retroactively affect us.
