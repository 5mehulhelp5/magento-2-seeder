# Seeder Facade Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add an abstract `Seeder` base class plus a `SeedBuilder` fluent API so class seeders read like Laravel (`$this->orders()->count(50)->create()`), without renaming anything or breaking existing seeders.

**Architecture:** Two new classes in `RunAsRoot\Seeder\` namespace. `Seeder` (abstract) holds injected pools and exposes short entry methods (`customers()`, `products()`, `orders()`, `categories()`, `cms()`, `seed()`) that each return a fresh `SeedBuilder`. `SeedBuilder` is a throwaway value object with a fluent API (`count`, `with`, `using`, `subtype`, `create`). All existing plumbing (`SeederInterface`, `SeederRunner`, `SeederDiscovery`, `ArraySeederAdapter`, `GenerateRunner`) stays untouched.

**Tech Stack:** PHP 8.2+, Magento 2 / Mage-OS, PHPUnit 10, Faker, Symfony Console. Existing project conventions: `final class` for unit tests, `snake_case` test method names, strict types, readonly promoted ctor args.

**Design reference:** `docs/plans/2026-04-19-seeder-facade-design.md`

---

## Conventions for every task

- `declare(strict_types=1);` at top of every new PHP file.
- Namespace: `RunAsRoot\Seeder` for production code, `RunAsRoot\Seeder\Test\Unit` for tests.
- Unit tests: `final class`, extend `PHPUnit\Framework\TestCase`, method names `test_snake_case`.
- Run tests via `vendor/bin/phpunit` from the repo root (`/Users/david/Herd/seeder`).
- Commit after every task (not every step). Branch: create `feat/seeder-facade` from `main` before Task 1.

**One-time setup before Task 1:**

```bash
cd /Users/david/Herd/seeder
git checkout main
git pull --ff-only  # only if you want to grab latest; skip if offline
git checkout -b feat/seeder-facade
```

---

## Task 1: `SeedBuilder` — bare single `create()` path

Covers the simplest matrix row: `$builder->create()` with no `count`/`with`/`using` → generator produces data, handler persists it, id returned.

**Files:**
- Create: `src/SeedBuilder.php`
- Create: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/SeedBuilderTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit;

use Faker\Generator as FakerGenerator;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;
use RunAsRoot\Seeder\SeedBuilder;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeedBuilderTest extends TestCase
{
    public function test_create_without_overrides_uses_generator_and_returns_ids(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->once())
            ->method('create')
            ->with(['email' => 'gen@example.com'])
            ->willReturn(42);

        $generator = $this->createMock(DataGeneratorInterface::class);
        $generator->expects($this->once())
            ->method('generate')
            ->willReturn(['email' => 'gen@example.com']);

        $builder = new SeedBuilder(
            'customer',
            new EntityHandlerPool(['customer' => $handler]),
            new DataGeneratorPool(['customer' => $generator]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        );

        $this->assertSame([42], $builder->create());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: FAIL with `Class "RunAsRoot\Seeder\SeedBuilder" not found`.

**Step 3: Write minimal implementation**

Create `src/SeedBuilder.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder;

use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeedBuilder
{
    private int $count = 1;

    public function __construct(
        private readonly string $type,
        private readonly EntityHandlerPool $handlers,
        private readonly DataGeneratorPool $generators,
        private readonly FakerFactory $fakerFactory,
        private readonly GeneratedDataRegistry $registry,
    ) {
    }

    /** @return int[] created ids */
    public function create(): array
    {
        $baseType = explode('.', $this->type, 2)[0];
        $handler = $this->handlers->get($baseType);
        $generator = $this->generators->get($baseType);
        $faker = $this->fakerFactory->create();

        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $generator->generate($faker, $this->registry);
            $ids[] = $handler->create($data);
        }

        return $ids;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: PASS (1 test, 1 assertion).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — single generator-backed create()"
```

---

## Task 2: `SeedBuilder::count()` — mass create

**Files:**
- Modify: `tests/Unit/SeedBuilderTest.php` — add one test method
- (no production change if Task 1's impl already uses `$this->count`; we add the fluent setter)

**Step 1: Write the failing test**

Add to `SeedBuilderTest`:

```php
public function test_count_creates_n_entities_and_returns_all_ids(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->expects($this->exactly(3))
        ->method('create')
        ->willReturnOnConsecutiveCalls(1, 2, 3);

    $generator = $this->createMock(DataGeneratorInterface::class);
    $generator->expects($this->exactly(3))
        ->method('generate')
        ->willReturn(['k' => 'v']);

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool(['customer' => $generator]),
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $this->assertSame([1, 2, 3], $builder->count(3)->create());
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_count_creates_n_entities`
Expected: FAIL — `count()` method undefined.

**Step 3: Implement**

Add to `src/SeedBuilder.php`:

```php
public function count(int $n): self
{
    $this->count = $n;
    return $this;
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all SeedBuilder tests pass (2 tests, 2+ assertions).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — count()"
```

---

## Task 3: `SeedBuilder::with()` — static overrides merged per iteration

**Files:**
- Modify: `src/SeedBuilder.php`
- Modify: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

Add to `SeedBuilderTest`:

```php
public function test_with_merges_static_data_over_generator_output(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->expects($this->once())
        ->method('create')
        ->with(['email' => 'gen@example.com', 'firstname' => 'Override'])
        ->willReturn(7);

    $generator = $this->createMock(DataGeneratorInterface::class);
    $generator->method('generate')->willReturn(
        ['email' => 'gen@example.com', 'firstname' => 'Generated']
    );

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool(['customer' => $generator]),
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $this->assertSame([7], $builder->with(['firstname' => 'Override'])->create());
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_with_merges_static_data`
Expected: FAIL — `with()` undefined.

**Step 3: Implement**

Modify `src/SeedBuilder.php`:

```php
/** @var array<string, mixed> */
private array $with = [];

public function with(array $data): self
{
    $this->with = $data;
    return $this;
}
```

Update `create()`'s inner loop to merge:

```php
$data = $generator->generate($faker, $this->registry);
$data = array_replace($data, $this->with);
$ids[] = $handler->create($data);
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all green (3 tests).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — with() static overrides"
```

---

## Task 4: `SeedBuilder::using()` — dynamic per-iteration callback

**Files:**
- Modify: `src/SeedBuilder.php`
- Modify: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

```php
public function test_using_callback_is_called_per_iteration_and_overrides_with(): void
{
    $received = [];

    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->method('create')->willReturnCallback(
        function (array $data) use (&$received): int {
            $received[] = $data;
            return count($received);
        }
    );

    $generator = $this->createMock(DataGeneratorInterface::class);
    $generator->method('generate')->willReturn(['base' => 'b']);

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool(['customer' => $generator]),
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $builder
        ->count(2)
        ->with(['w' => 'static'])
        ->using(fn(int $i) => ['i' => $i, 'w' => 'dynamic'])
        ->create();

    $this->assertSame(
        [
            ['base' => 'b', 'w' => 'dynamic', 'i' => 0],
            ['base' => 'b', 'w' => 'dynamic', 'i' => 1],
        ],
        $received
    );
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_using_callback`
Expected: FAIL — `using()` undefined.

**Step 3: Implement**

Add to `src/SeedBuilder.php`:

```php
/** @var (callable(int, \Faker\Generator): array)|null */
private $using = null;

public function using(callable $fn): self
{
    $this->using = $fn;
    return $this;
}
```

Update the loop in `create()`:

```php
for ($i = 0; $i < $this->count; $i++) {
    $data = $generator->generate($faker, $this->registry);
    $data = array_replace($data, $this->with);
    if ($this->using !== null) {
        $data = array_replace($data, ($this->using)($i, $faker));
    }
    $ids[] = $handler->create($data);
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all green (4 tests).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — using() callback"
```

---

## Task 5: `SeedBuilder::subtype()` — forces subtype on `SubtypeAwareInterface` generators

Matches `GenerateRunner::generateType()` behavior (src/Service/GenerateRunner.php:61-71, 142-144).

**Files:**
- Modify: `src/SeedBuilder.php`
- Modify: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

```php
public function test_subtype_sets_and_clears_forced_subtype_on_subtype_aware_generator(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->method('create')->willReturn(1);

    $generator = $this->createMock(
        \RunAsRoot\Seeder\Api\SubtypeAwareInterface::class
        // Intersection with DataGeneratorInterface via double mock
    );
    // Use a real hybrid via anon class to satisfy both contracts:
    $generator = new class implements
        \RunAsRoot\Seeder\Api\DataGeneratorInterface,
        \RunAsRoot\Seeder\Api\SubtypeAwareInterface {
        public ?string $forced = null;
        public array $forcedHistory = [];
        public function getType(): string { return 'product'; }
        public function getOrder(): int { return 20; }
        public function generate(\Faker\Generator $f, \RunAsRoot\Seeder\Service\GeneratedDataRegistry $r): array
        { $this->forcedHistory[] = $this->forced; return ['sku' => 'X']; }
        public function getDependencies(): array { return []; }
        public function getDependencyCount(string $t, int $c): int { return 0; }
        public function setForcedSubtype(?string $subtype): void { $this->forced = $subtype; }
    };

    $builder = new SeedBuilder(
        'product',
        new EntityHandlerPool(['product' => $handler]),
        new DataGeneratorPool(['product' => $generator]),
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $builder->subtype('bundle')->create();

    $this->assertSame(['bundle'], $generator->forcedHistory);
    $this->assertNull($generator->forced, 'subtype must be cleared after create()');
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_subtype_sets_and_clears`
Expected: FAIL — `subtype()` undefined.

**Step 3: Implement**

Add to `src/SeedBuilder.php`:

```php
private ?string $subtype = null;

public function subtype(string $subtype): self
{
    $this->subtype = $subtype;
    return $this;
}
```

Wrap the loop in `create()` with subtype set/clear (wrap in try/finally):

```php
use RunAsRoot\Seeder\Api\SubtypeAwareInterface;
// ...

public function create(): array
{
    $baseType = explode('.', $this->type, 2)[0];
    $dottedSubtype = explode('.', $this->type, 2)[1] ?? null;
    $effectiveSubtype = $this->subtype ?? $dottedSubtype;

    $handler = $this->handlers->get($baseType);
    $generator = $this->generators->get($baseType);
    $faker = $this->fakerFactory->create();

    $subtypeAware = $effectiveSubtype !== null && $generator instanceof SubtypeAwareInterface;
    if ($subtypeAware) {
        $generator->setForcedSubtype($effectiveSubtype);
    }

    try {
        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $generator->generate($faker, $this->registry);
            $data = array_replace($data, $this->with);
            if ($this->using !== null) {
                $data = array_replace($data, ($this->using)($i, $faker));
            }
            $ids[] = $handler->create($data);
        }
        return $ids;
    } finally {
        if ($subtypeAware) {
            $generator->setForcedSubtype(null);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all green (5 tests).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — subtype()"
```

---

## Task 6: `SeedBuilder::create()` — raw data path (no generator registered + `with()` provided)

Per design Section 2: `with()` + no generator → write raw data directly to handler (mirrors `ArraySeederAdapter` behavior).

**Files:**
- Modify: `src/SeedBuilder.php`
- Modify: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

```php
public function test_create_with_only_with_data_and_no_generator_writes_raw(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->expects($this->once())
        ->method('create')
        ->with(['email' => 'raw@example.com'])
        ->willReturn(9);

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool([]), // no generator registered
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $this->assertSame([9], $builder->with(['email' => 'raw@example.com'])->create());
}

public function test_create_without_generator_and_without_with_throws(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool([]),
        new FakerFactory(),
        new GeneratedDataRegistry(),
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No data generator for type "customer"');

    $builder->create();
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: FAIL — first test currently throws `InvalidArgumentException` from `DataGeneratorPool::get()`.

**Step 3: Implement**

Modify `src/SeedBuilder.php` `create()` to branch on generator presence:

```php
public function create(): array
{
    $baseType = explode('.', $this->type, 2)[0];
    $dottedSubtype = explode('.', $this->type, 2)[1] ?? null;
    $effectiveSubtype = $this->subtype ?? $dottedSubtype;

    $handler = $this->handlers->get($baseType);
    $hasGenerator = $this->generators->has($baseType);

    if (!$hasGenerator && $this->with === [] && $this->using === null) {
        throw new \RuntimeException(
            "No data generator for type \"{$baseType}\"; pass data via ->with(...)"
        );
    }

    $generator = $hasGenerator ? $this->generators->get($baseType) : null;
    $faker = $this->fakerFactory->create();

    $subtypeAware = $effectiveSubtype !== null
        && $generator instanceof SubtypeAwareInterface;
    if ($subtypeAware) {
        $generator->setForcedSubtype($effectiveSubtype);
    }

    try {
        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $data = $generator !== null
                ? $generator->generate($faker, $this->registry)
                : [];
            $data = array_replace($data, $this->with);
            if ($this->using !== null) {
                $data = array_replace($data, ($this->using)($i, $faker));
            }
            $ids[] = $handler->create($data);
        }
        return $ids;
    } finally {
        if ($subtypeAware) {
            $generator->setForcedSubtype(null);
        }
    }
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all green (7 tests).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — raw data path + clear error"
```

---

## Task 7: Registry writeback

Per design Section 3: created entities written to `GeneratedDataRegistry` (with `id` key) so later builders in the same `run()` can reference them via generators.

**Files:**
- Modify: `src/SeedBuilder.php`
- Modify: `tests/Unit/SeedBuilderTest.php`

**Step 1: Write the failing test**

```php
public function test_create_writes_created_entity_with_id_to_registry(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->method('create')->willReturnOnConsecutiveCalls(11, 12);

    $generator = $this->createMock(DataGeneratorInterface::class);
    $generator->method('generate')->willReturn(['email' => 'x@y.com']);

    $registry = new GeneratedDataRegistry();

    $builder = new SeedBuilder(
        'customer',
        new EntityHandlerPool(['customer' => $handler]),
        new DataGeneratorPool(['customer' => $generator]),
        new FakerFactory(),
        $registry,
    );

    $builder->count(2)->create();

    $this->assertSame(
        [
            ['email' => 'x@y.com', 'id' => 11],
            ['email' => 'x@y.com', 'id' => 12],
        ],
        $registry->getAll('customer')
    );
}

public function test_registry_writeback_uses_base_type_for_subtyped_calls(): void
{
    $handler = $this->createMock(EntityHandlerInterface::class);
    $handler->method('create')->willReturn(77);

    $generator = $this->createMock(DataGeneratorInterface::class);
    $generator->method('generate')->willReturn(['sku' => 'X']);

    $registry = new GeneratedDataRegistry();

    $builder = new SeedBuilder(
        'product.bundle',
        new EntityHandlerPool(['product' => $handler]),
        new DataGeneratorPool(['product' => $generator]),
        new FakerFactory(),
        $registry,
    );

    $builder->create();

    $this->assertCount(1, $registry->getAll('product'));
    $this->assertSame([], $registry->getAll('product.bundle'));
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: FAIL — registry empty, no writeback yet.

**Step 3: Implement**

In `src/SeedBuilder.php` loop (inside `try`), after `$handler->create($data)`:

```php
$id = $handler->create($data);
$data['id'] = $id;
$this->registry->add($baseType, $data);
$ids[] = $id;
```

(Replace the old `$ids[] = $handler->create($data);` line.)

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeedBuilderTest`
Expected: all green (9 tests).

**Step 5: Commit**

```bash
git add src/SeedBuilder.php tests/Unit/SeedBuilderTest.php
git commit -m "feat(seeder): SeedBuilder — registry writeback with id"
```

---

## Task 8: Abstract `Seeder` base class

**Files:**
- Create: `src/Seeder.php`
- Create: `tests/Unit/SeederTest.php`

**Step 1: Write the failing test**

Create `tests/Unit/SeederTest.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit;

use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\SeedBuilder;
use RunAsRoot\Seeder\Seeder;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

final class SeederTest extends TestCase
{
    private function makeSubject(): Seeder
    {
        return new class(
            new EntityHandlerPool([]),
            new DataGeneratorPool([]),
            new FakerFactory(),
            new GeneratedDataRegistry(),
        ) extends Seeder {
            public function getType(): string { return 't'; }
            public function getOrder(): int { return 1; }
            public function run(): void {}

            public function publicCustomers(): SeedBuilder  { return $this->customers(); }
            public function publicProducts(): SeedBuilder   { return $this->products(); }
            public function publicOrders(): SeedBuilder     { return $this->orders(); }
            public function publicCategories(): SeedBuilder { return $this->categories(); }
            public function publicCms(): SeedBuilder        { return $this->cms(); }
            public function publicSeed(string $t): SeedBuilder { return $this->seed($t); }
        };
    }

    public function test_customers_returns_builder_bound_to_customer_type(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCustomers());
    }

    public function test_products_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicProducts());
    }

    public function test_orders_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicOrders());
    }

    public function test_categories_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCategories());
    }

    public function test_cms_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicCms());
    }

    public function test_seed_with_custom_type_returns_builder(): void
    {
        $this->assertInstanceOf(SeedBuilder::class, $this->makeSubject()->publicSeed('wishlist'));
    }

    public function test_each_call_returns_a_fresh_builder(): void
    {
        $subject = $this->makeSubject();
        $this->assertNotSame($subject->publicCustomers(), $subject->publicCustomers());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter SeederTest`
Expected: FAIL — `Class "RunAsRoot\Seeder\Seeder" not found`.

**Step 3: Implement**

Create `src/Seeder.php`:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder;

use RunAsRoot\Seeder\Api\SeederInterface;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

abstract class Seeder implements SeederInterface
{
    public function __construct(
        protected readonly EntityHandlerPool $handlers,
        protected readonly DataGeneratorPool $generators,
        protected readonly FakerFactory $fakerFactory,
        protected readonly GeneratedDataRegistry $registry,
    ) {
    }

    protected function customers(): SeedBuilder  { return $this->makeBuilder('customer'); }
    protected function products(): SeedBuilder   { return $this->makeBuilder('product'); }
    protected function orders(): SeedBuilder     { return $this->makeBuilder('order'); }
    protected function categories(): SeedBuilder { return $this->makeBuilder('category'); }
    protected function cms(): SeedBuilder        { return $this->makeBuilder('cms'); }

    protected function seed(string $type): SeedBuilder
    {
        return $this->makeBuilder($type);
    }

    private function makeBuilder(string $type): SeedBuilder
    {
        return new SeedBuilder(
            $type,
            $this->handlers,
            $this->generators,
            $this->fakerFactory,
            $this->registry,
        );
    }

    abstract public function getType(): string;
    abstract public function getOrder(): int;
    abstract public function run(): void;
}
```

**Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter SeederTest`
Expected: all green (7 tests).

**Step 5: Commit**

```bash
git add src/Seeder.php tests/Unit/SeederTest.php
git commit -m "feat(seeder): abstract Seeder base class with fluent entry methods"
```

---

## Task 9: Example seeder extending `Seeder`

**Files:**
- Create: `examples/FluentOrderSeeder.php`

**Step 1: Write the example**

```php
<?php

declare(strict_types=1);

use RunAsRoot\Seeder\Seeder;

final class FluentOrderSeeder extends Seeder
{
    public function getType(): string
    {
        return 'order';
    }

    public function getOrder(): int
    {
        return 40;
    }

    public function run(): void
    {
        $this->orders()
            ->count(10)
            ->with([
                'items' => [
                    ['sku' => 'TSHIRT-001', 'qty' => 2],
                ],
            ])
            ->create();
    }
}
```

**Step 2: Verify it's discoverable**

Sanity check — `SeederDiscovery` matches `*Seeder.php` (src/Service/SeederDiscovery.php:20), so filename `FluentOrderSeeder.php` qualifies.

No automated test at this step — covered by the integration smoke test next.

**Step 3: Commit**

```bash
git add examples/FluentOrderSeeder.php
git commit -m "docs(examples): FluentOrderSeeder using abstract Seeder base class"
```

---

## Task 10: Integration smoke — extend existing `db:seed` CLI test

The repo already has a CI integration test for `db:seed` (commit `c67697b`, via Graycore). Confirm the new facade works end-to-end by running `db:seed` in an env that includes a class seeder extending `Seeder`.

**Files:**
- Modify (or verify existing): the Graycore integration harness entry point for this module.

**Step 1: Locate the integration test**

```bash
git show c67697b --stat
```

Identify the file that fires `bin/magento db:seed`. Likely lives under a CI fixture or a `.github/` workflow file plus a fixture directory.

**Step 2: Add a fixture seeder**

In the fixture seeder directory used by the integration test, drop a copy of `examples/FluentOrderSeeder.php` (or symlink, whichever the harness expects), so the CI run exercises the new base class.

**Step 3: Push branch and watch CI**

```bash
git add <fixture path>
git commit -m "test(integration): exercise Seeder base class in db:seed smoke"
git push -u origin feat/seeder-facade
```

Then open the PR and confirm the `check-extension` workflow passes (as it did on `dbda9fb`).

**Step 4: If CI fails**

Stop. Follow superpowers:systematic-debugging. Likely causes:
- DI can't resolve `GeneratedDataRegistry` or `FakerFactory` into the subclass ctor — add explicit `<type>` entries in `etc/di.xml` if needed (shouldn't be: they're concrete classes with zero-arg constructors — Magento auto-wires).
- Fixture path wrong (the Graycore env mounts a different path than dev).

**Step 5: Commit/push fixes, then proceed.**

*(No git commit here if nothing changed after push; CI run is the verification.)*

---

## Task 11: README + CHANGELOG

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`

**Step 1: README — rewrite the "Class-Based (powerful)" section**

Replace the existing block (README.md ~line 106-134) with:

````markdown
### Class-Based (fluent, recommended)

For complex scenarios, extend `RunAsRoot\Seeder\Seeder` and use the fluent builder:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use RunAsRoot\Seeder\Seeder;

class MassOrderSeeder extends Seeder
{
    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $this->orders()
            ->count(50)
            ->with(['items' => [['sku' => 'TSHIRT-001', 'qty' => 2]]])
            ->create();
    }
}
```

Available builder entry points: `customers()`, `products()`, `orders()`, `categories()`, `cms()`, plus `seed('custom_type')` for types registered via `di.xml`.

Builder methods:

| Method | Purpose |
|---|---|
| `->count(int $n)` | How many to create |
| `->with(array $data)` | Static overrides merged into each iteration |
| `->using(callable $fn)` | Per-iteration callback: `fn(int $i, Faker\Generator $faker): array` |
| `->subtype(string $s)` | Force subtype (e.g. `'bundle'` for products, `'complete'` for orders) |
| `->create()` | Executes and returns `int[]` of created ids |

Precedence (most specific wins): `using()` > `with()` > generator Faker defaults.

### Class-Based (low-level)

If you need full control, implement `SeederInterface` directly and inject `EntityHandlerPool`:

```php
class CustomSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}
    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }
    public function run(): void {
        $this->handlerPool->get('order')->create([...]);
    }
}
```
````

**Step 2: CHANGELOG — prepend entry**

At the top of `CHANGELOG.md` under a new `## [Unreleased]` section (or insert under the existing one if present):

```markdown
### Added
- Abstract `RunAsRoot\Seeder\Seeder` base class so class seeders skip the `EntityHandlerPool` boilerplate.
- Fluent `RunAsRoot\Seeder\SeedBuilder` API: `$this->orders()->count(50)->with([...])->using(fn) ->create()`.
- `examples/FluentOrderSeeder.php` demonstrating the new style.
```

**Step 3: Commit**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: document Seeder base class and SeedBuilder fluent API"
```

---

## Task 12: Full test sweep + static analysis

**Files:** none modified; verification only.

**Step 1: Run the full suite**

```bash
vendor/bin/phpunit
```

Expected: all green, zero failures, zero errors.

**Step 2: Run phpcs (repo uses Magento coding standard; `examples/` is excluded per commit `dbda9fb`)**

```bash
vendor/bin/phpcs src/ tests/
```

Expected: no violations. Fix any reported issues and re-commit with message `style: phpcs`.

**Step 3: Run phpstan if configured**

```bash
test -f phpstan.neon && vendor/bin/phpstan analyse src tests || echo "no phpstan config"
```

Fix any errors surfaced (likely around callable signatures on `using()`).

**Step 4: Commit (only if fixes were needed)**

```bash
git add -p
git commit -m "style: satisfy phpcs/phpstan for new facade"
```

---

## Task 13: Open PR

**Files:** none.

**Step 1: Push and open PR**

```bash
git push -u origin feat/seeder-facade
gh pr create --title "feat: abstract Seeder base class + SeedBuilder fluent API" --body "$(cat <<'EOF'
## Summary
- Adds `RunAsRoot\Seeder\Seeder` abstract base class + `RunAsRoot\Seeder\SeedBuilder` fluent API.
- Class seeders now read like Laravel: `$this->orders()->count(50)->with([...])->create()`.
- Fully backward compatible — existing `implements SeederInterface` seeders untouched.

Design: `docs/plans/2026-04-19-seeder-facade-design.md`
Plan: `docs/plans/2026-04-19-seeder-facade-plan.md`

## Test plan
- [ ] `vendor/bin/phpunit` — all unit tests green
- [ ] Graycore `check-extension` CI green
- [ ] `db:seed` smoke run includes a class extending `Seeder` and succeeds
- [ ] README example compiles and runs
EOF
)"
```

**Step 2: Wait for CI, respond to review.**

---

## Summary of files

**Created:**
- `src/Seeder.php`
- `src/SeedBuilder.php`
- `tests/Unit/SeederTest.php`
- `tests/Unit/SeedBuilderTest.php`
- `examples/FluentOrderSeeder.php`

**Modified:**
- `README.md` (Class-Based section)
- `CHANGELOG.md` (Unreleased → Added)
- Integration-test fixture directory (Task 10 — path TBD by inspecting `c67697b`)

**Unchanged (important):**
- All of `src/Api/*`
- All of `src/Service/*`
- All of `src/EntityHandler/*`, `src/DataGenerator/*`
- `etc/di.xml`, `etc/module.xml`
- `src/Console/Command/*`
