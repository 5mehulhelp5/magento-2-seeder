# Commerce Wordlist Refresh

Wordlists in this directory are ported verbatim from `@faker-js/faker`.
FakerJS updates these lists rarely (2–3 entries/year). Refresh manually
when output looks stale.

## Current port source

See `NOTICE` at repo root for the exact upstream commit hash.

## How to refresh (en_US)

Upstream: https://github.com/faker-js/faker/tree/next/src/locales/en/commerce

1. Open each of these two files on the FakerJS `next` branch:
   - `product_name.ts` (contains `adjective`, `material`, `product`, `pattern` keys)
   - `department.ts`
2. Copy each string array out of the TypeScript source (drop the `export default` wrapper / object keys).
3. Paste each into the matching `private const` block in `EnUs.php`:
   - `product_name.adjective` → `ADJECTIVES`
   - `product_name.material` → `MATERIALS`
   - `product_name.product` → `PRODUCTS`
   - `department` → `DEPARTMENTS`
4. Update the commit hash in `NOTICE` to the current upstream HEAD.
5. Run `composer phpstan && vendor/bin/phpunit`.
6. Commit: `chore(faker-commerce): refresh en_US wordlists from upstream <hash>`.

Note: FakerJS may split `product_name.ts` into separate files
(`product_name/{adjective,material,product}.ts`) in a future refactor.
If you find the object-per-file layout missing, check the split-file
layout at `src/locales/en/commerce/product_name/*.ts` instead.

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
