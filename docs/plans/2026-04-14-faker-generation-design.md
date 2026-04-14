# Faker-Powered Data Generation — Design Document

## Overview

Extend the Magento 2 Database Seeder with automatic data generation using `fakerphp/faker`. Users can say `bin/magento db:seed --generate=order:1000` and the tool generates realistic fake data — including product images, customer addresses, and all dependent entities — without writing any seeder files.

## Goals

- Count-based generation: `--generate=order:1000` creates 1000 orders with all dependencies
- Realistic data via Faker: names, emails, addresses, product descriptions, prices
- Product images downloaded from `picsum.photos`
- Smart dependency resolution: ordering 1000 orders auto-generates products, customers, categories
- Configurable locale (`--locale=de_DE`) and deterministic seed (`--seed=42`)
- Works via CLI flags AND seeder files with `count` key

## New File Structure

```
src/
├── Api/
│   └── DataGeneratorInterface.php
├── DataGenerator/
│   ├── CustomerDataGenerator.php
│   ├── CategoryDataGenerator.php
│   ├── ProductDataGenerator.php
│   ├── OrderDataGenerator.php
│   └── CmsDataGenerator.php
├── Service/
│   ├── DataGeneratorPool.php
│   ├── DependencyResolver.php
│   ├── GenerateRunner.php
│   ├── GeneratedDataRegistry.php
│   └── FakerFactory.php
```

## Core Interfaces

### DataGeneratorInterface

```php
interface DataGeneratorInterface
{
    public function getType(): string;
    public function generate(): array;           // returns ONE data array for handler's create()
    public function getDependencies(): array;     // e.g., ['product', 'customer']
    public function getDefaultRatio(int $parentCount): int;  // e.g., 1000 orders → 200 customers
}
```

### GeneratedDataRegistry

In-memory store so generators can reference previously created entities:

```php
class GeneratedDataRegistry
{
    public function add(string $type, array $entityData): void;
    public function getAll(string $type): array;
    public function getRandom(string $type): array;
}
```

### FakerFactory

Creates a configured Faker instance:

```php
class FakerFactory
{
    public function create(string $locale = 'en_US', ?int $seed = null): \Faker\Generator;
}
```

## Dependency Resolution

`DependencyResolver` takes user-specified counts and fills in gaps using generator ratios.

**Example:** `--generate=order:1000`

```
DependencyResolver walks the graph:
  order depends on [product, customer]
  product depends on [category]
  customer depends on []
  category depends on []

Result:
  category: 10
  product: 50
  customer: 200
  order: 1000
```

**User overrides always win.** `--generate=customer:500,order:1000` uses 500 customers.

### Default Ratios

| Requested Entity | Dependency | Ratio |
|-----------------|------------|-------|
| order | product | ~1:20 (1000 orders → 50 products) |
| order | customer | ~1:5 (1000 orders → 200 customers) |
| product | category | ~1:5 (100 products → 20 categories) |

Ratios live in each generator's `getDefaultRatio()` method.

### Execution Order

Reuses existing priority: categories (10) → products (20) → customers (30) → orders (40) → CMS (50).

## CLI Interface

### New Flags

```bash
bin/magento db:seed --generate=order:1000
bin/magento db:seed --generate=order:1000,customer:500
bin/magento db:seed --generate=order:1000 --locale=de_DE
bin/magento db:seed --generate=order:1000 --seed=42
bin/magento db:seed --generate=order:1000 --fresh
```

`--generate` and regular seeder file discovery are mutually exclusive.

### Seeder File Format

```php
<?php
return [
    'type' => 'order',
    'count' => 1000,
    'locale' => 'de_DE',   // optional
    'seed' => 42,           // optional
];
```

When `ArraySeederAdapter` sees `count` instead of `data`, it delegates to the data generation pipeline.

## Data Generators — Detail

### CustomerDataGenerator

- Faker: first/last name, email, DOB, gender, group
- 1-2 addresses per customer: street, city, region, postcode, country, telephone
- One address as default billing, one as default shipping
- `CustomerHandler::create()` enhanced to accept `addresses` array

### CategoryDataGenerator

- Realistic category names (department/commerce words)
- Randomly nests under existing categories or default (ID 2) to build a tree
- `is_active`, `description`, `url_key`

### ProductDataGenerator

- SKU, name, description, short description, price (realistic ranges), weight
- Downloads image from `picsum.photos/800/800` per product
- Saves to `pub/media/catalog/product/import/`
- Sets `category_ids` from previously generated categories (via registry)
- Stock qty (random 10-500)
- `ProductHandler::create()` enhanced to handle `image` key via media gallery API

### OrderDataGenerator

- Picks random customer email from registry
- Picks 1-5 random products from registry
- Random quantities per item
- Uses customer's address data for billing/shipping

### CmsDataGenerator

- `seed-` prefixed identifiers
- Faker paragraphs wrapped in basic HTML
- Random mix of pages and blocks

## Image Handling

1. Request random image from `https://picsum.photos/800/800`
2. Save to temp file, move to `pub/media/catalog/product/import/`
3. `ProductHandler` attaches via `ProductAttributeMediaGalleryManagementInterface`
4. Set as base image, small image, and thumbnail
5. **Failure: log warning, skip image, product still created**

## GenerateRunner Flow

```
--generate=order:1000
        │
        ▼
  SeedCommand (parse --generate, --locale, --seed)
        │
        ▼
  FakerFactory (create Faker with locale + seed)
        │
        ▼
  DependencyResolver (resolve: category:10, product:50, customer:200, order:1000)
        │
        ▼
  GenerateRunner (for each type in dependency order):
        ├── DataGenerator::generate() × count
        ├── EntityHandler::create() for each generated item
        └── GeneratedDataRegistry::add() to track created data
        │
        ▼
  Output summary
```

## Changes to Existing Code

- **`SeedCommand`**: Add `--generate`, `--locale`, `--seed` options. Route to `GenerateRunner` when `--generate` is present.
- **`ArraySeederAdapter`**: Detect `count` key, delegate to generation pipeline instead of iterating `data`.
- **`CustomerHandler::create()`**: Accept optional `addresses` array, create addresses via `AddressRepositoryInterface`.
- **`ProductHandler::create()`**: Accept optional `image` key, attach via media gallery API.
- **`composer.json`**: Add `fakerphp/faker` to `require`.
- **`di.xml`**: Wire `DataGeneratorPool` with generators.

## Design Decisions

1. **Generators return data arrays, not entities** — keeps generators decoupled from Magento APIs. Handlers do the heavy lifting.
2. **Registry for cross-generator references** — simple in-memory store, no DB queries to find "recently created" entities.
3. **`picsum.photos` for images** — real photos look better than GD placeholders. Graceful fallback on failure.
4. **Mutually exclusive with regular seeders** — `--generate` skips discovery to avoid confusion.
5. **Ratios in generators** — each generator knows its own sensible defaults, easy to tune.
