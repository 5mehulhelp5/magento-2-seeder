# Cart Rules, Wishlists, Newsletter Subscribers — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add three new generator/handler pairs (`cart_rule`, `wishlist`, `newsletter_subscriber`) following the existing module pattern, plus a small tweak so `CustomerDataGenerator` emits 1–3 addresses.

**Architecture:** Each new type = one `DataGeneratorInterface` + one `EntityHandlerInterface` implementation, registered in `src/etc/di.xml`. No runner, builder, or CLI changes. Tests are TDD: unit tests per generator/handler collaborator, plus integration smoke tests against a real Mage-OS install via the existing `Test/Integration/` harness.

**Tech Stack:** PHP 8.1+, Magento 2, `fakerphp/faker`, PHPUnit 10.

**Source design:** `docs/plans/2026-04-20-cart-rule-wishlist-newsletter-design.md`

---

## Ground rules

- Unit tests are **final** classes; methods use **snake_case** (house style).
- One logical change per commit. Commit after every green test batch.
- Use the `run-as-root` namespace (`RunAsRoot\Seeder\*`), never personal handles.
- Phase order is chosen smallest-first so blockers appear early: customer-address tweak → newsletter → cart rule → wishlist → combined integration.
- After every phase, run `composer phpstan` and `composer phpcs` locally. Fix any warning before committing.
- **Do NOT push to origin.** User will push manually.

---

## Phase 1 — Customer address tweak

Warm-up. No new files; exercises the TDD loop against the existing generator.

### Task 1: Update CustomerDataGenerator unit test for multiple addresses

**Files:**
- Modify: `Test/Unit/DataGenerator/CustomerDataGeneratorTest.php`

**Step 1: Add failing test**

Append inside the existing `CustomerDataGeneratorTest` class:

```php
public function test_generate_produces_one_to_three_addresses(): void
{
    $faker = \Faker\Factory::create('en_US');
    $registry = new GeneratedDataRegistry();
    $generator = new CustomerDataGenerator();

    $counts = [];
    for ($i = 0; $i < 200; $i++) {
        $faker->seed($i);
        $data = $generator->generate($faker, $registry);
        $count = count($data['addresses']);
        $this->assertGreaterThanOrEqual(1, $count, "Seed {$i}: at least 1 address required");
        $this->assertLessThanOrEqual(3, $count, "Seed {$i}: no more than 3 addresses");
        $counts[$count] = ($counts[$count] ?? 0) + 1;
    }

    $this->assertArrayHasKey(1, $counts, '1-address outcomes expected in distribution');
    $this->assertGreaterThan(10, $counts[2] ?? 0, '2-address outcomes expected');
    $this->assertGreaterThan(10, $counts[3] ?? 0, '3-address outcomes expected');
}

public function test_generate_first_address_is_default_billing_and_shipping(): void
{
    $faker = \Faker\Factory::create('en_US');
    $faker->seed(7);
    $registry = new GeneratedDataRegistry();

    $data = (new CustomerDataGenerator())->generate($faker, $registry);

    $this->assertTrue($data['addresses'][0]['default_billing']);
    $this->assertTrue($data['addresses'][0]['default_shipping']);

    for ($i = 1, $n = count($data['addresses']); $i < $n; $i++) {
        $this->assertFalse($data['addresses'][$i]['default_billing']);
        $this->assertFalse($data['addresses'][$i]['default_shipping']);
    }
}
```

**Step 2: Run tests, confirm both FAIL**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/CustomerDataGeneratorTest.php --filter 'address'`
Expected: Two failures — current generator always returns exactly 1 address.

**Step 3: Update `CustomerDataGenerator::generate()`**

**Files:**
- Modify: `src/DataGenerator/CustomerDataGenerator.php`

Replace the body of `generate()` so it builds a random 1–3 count of addresses. First one is default billing/shipping; others are not. Keep the existing single-address helper if useful; otherwise inline:

```php
public function generate(Generator $faker, GeneratedDataRegistry $registry): array
{
    $firstname = $faker->firstName();
    $lastname = $faker->lastName();

    $addressCount = $faker->numberBetween(1, 3);
    $addresses = [];
    for ($i = 0; $i < $addressCount; $i++) {
        $addresses[] = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'street' => [$faker->streetAddress()],
            'city' => $faker->city(),
            'region_id' => $faker->numberBetween(1, 65),
            'postcode' => $faker->postcode(),
            'country_id' => 'US',
            'telephone' => $this->sanitizeTelephone($faker->phoneNumber()),
            'default_billing' => $i === 0,
            'default_shipping' => $i === 0,
        ];
    }

    return [
        'email' => $faker->unique()->safeEmail(),
        'firstname' => $firstname,
        'lastname' => $lastname,
        'password' => 'Test1234!',
        'dob' => $faker->date('Y-m-d', '-18 years'),
        'addresses' => $addresses,
    ];
}
```

**Step 4: Run full unit suite, confirm green**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/CustomerDataGeneratorTest.php`
Expected: All green. The pre-existing `test_generate_returns_valid_customer_data_with_addresses` and phone regex test should still pass (they examine index 0 only).

**Step 5: Commit**

```bash
git add src/DataGenerator/CustomerDataGenerator.php Test/Unit/DataGenerator/CustomerDataGeneratorTest.php
git commit -m "feat(customer): emit 1-3 addresses per generated customer"
```

---

## Phase 2 — Newsletter subscriber

Simplest new type: no dependencies, no coupon/relation complexity.

### Task 2: Add composer dependency for `magento/module-newsletter`

**Files:**
- Modify: `composer.json`
- Modify: `src/etc/module.xml`

**Step 1: Add module to `require`**

Under `require`, insert alphabetically:

```json
"magento/module-newsletter": "*",
```

**Step 2: Add stub entry** (so static analysis stays happy on CI without Mage-OS installed)

Under `repositories.magento-stubs.package`, insert alphabetically:

```json
{"name": "magento/module-newsletter", "version": "999.999.999", "type": "metapackage"},
```

**Step 3: Add sequence entry**

In `src/etc/module.xml`, inside `<sequence>`:

```xml
<module name="Magento_Newsletter"/>
```

**Step 4: Run composer validate + phpstan**

Run: `composer validate` → expect `./composer.json is valid`.
Run: `composer phpstan` → expect no new errors.

**Step 5: Commit**

```bash
git add composer.json src/etc/module.xml
git commit -m "chore(composer): add magento/module-newsletter dependency"
```

### Task 3: Write failing unit tests for `NewsletterSubscriberDataGenerator`

**Files:**
- Create: `Test/Unit/DataGenerator/NewsletterSubscriberDataGeneratorTest.php`

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\NewsletterSubscriberDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class NewsletterSubscriberDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_newsletter_subscriber(): void
    {
        $this->assertSame(
            'newsletter_subscriber',
            (new NewsletterSubscriberDataGenerator())->getType()
        );
    }

    public function test_get_order_returns_70(): void
    {
        $this->assertSame(70, (new NewsletterSubscriberDataGenerator())->getOrder());
    }

    public function test_get_dependencies_is_empty(): void
    {
        $this->assertSame([], (new NewsletterSubscriberDataGenerator())->getDependencies());
    }

    public function test_generate_without_customers_emits_guest_rows(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(1);
        $registry = new GeneratedDataRegistry();

        $data = (new NewsletterSubscriberDataGenerator())->generate($faker, $registry);

        $this->assertArrayHasKey('email', $data);
        $this->assertStringContainsString('@', $data['email']);
        $this->assertSame(0, $data['customer_id']);
        $this->assertSame(1, $data['store_id']);
        $this->assertSame(1, $data['subscriber_status']);
    }

    public function test_generate_with_customers_sometimes_links_to_customer(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 101, 'email' => 'seed-a@example.com']);
        $registry->add('customer', ['id' => 102, 'email' => 'seed-b@example.com']);
        $registry->add('customer', ['id' => 103, 'email' => 'seed-c@example.com']);

        $linked = 0;
        $guest = 0;
        for ($i = 0; $i < 200; $i++) {
            $faker->seed($i);
            $data = (new NewsletterSubscriberDataGenerator())->generate($faker, $registry);
            if ($data['customer_id'] > 0) {
                $linked++;
                $this->assertContains($data['email'], ['seed-a@example.com', 'seed-b@example.com', 'seed-c@example.com']);
                $this->assertContains($data['customer_id'], [101, 102, 103]);
            } else {
                $guest++;
                $this->assertSame(0, $data['customer_id']);
            }
        }

        $this->assertGreaterThan(40, $linked, 'Expected rough 50/50 split — linked too low');
        $this->assertGreaterThan(40, $guest, 'Expected rough 50/50 split — guest too low');
    }
}
```

**Step 2: Run the tests, confirm they FAIL**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/NewsletterSubscriberDataGeneratorTest.php`
Expected: `NewsletterSubscriberDataGenerator` class not found.

### Task 4: Implement `NewsletterSubscriberDataGenerator`

**Files:**
- Create: `src/DataGenerator/NewsletterSubscriberDataGenerator.php`

**Step 1: Create the generator**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class NewsletterSubscriberDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'newsletter_subscriber';
    }

    public function getOrder(): int
    {
        return 70;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $customers = $registry->getAll('customer');
        $linkToCustomer = !empty($customers) && $faker->boolean(50);

        if ($linkToCustomer) {
            $customer = $faker->randomElement($customers);

            return [
                'email' => $customer['email'],
                'store_id' => 1,
                'subscriber_status' => 1,
                'customer_id' => (int) $customer['id'],
            ];
        }

        return [
            'email' => $faker->unique()->safeEmail(),
            'store_id' => 1,
            'subscriber_status' => 1,
            'customer_id' => 0,
        ];
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return 0;
    }
}
```

**Step 2: Run tests, confirm PASS**

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/NewsletterSubscriberDataGeneratorTest.php`
Expected: All green.

**Step 3: Commit**

```bash
git add src/DataGenerator/NewsletterSubscriberDataGenerator.php Test/Unit/DataGenerator/NewsletterSubscriberDataGeneratorTest.php
git commit -m "feat(newsletter): add NewsletterSubscriberDataGenerator"
```

### Task 5: Implement `NewsletterSubscriberHandler`

**Files:**
- Create: `src/EntityHandler/NewsletterSubscriberHandler.php`

Handler unit tests here are low-value because the Magento collaborators (`SubscriberFactory`, resource model) are thin wrappers around DB I/O with limited behavior to assert. We validate via the integration smoke test instead (Task 12).

**Step 1: Create the handler**

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use Magento\Framework\App\ResourceConnection;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class NewsletterSubscriberHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly SubscriberFactory $subscriberFactory,
        private readonly ResourceConnection $resource,
    ) {
    }

    public function getType(): string
    {
        return 'newsletter_subscriber';
    }

    public function create(array $data): int
    {
        $subscriber = $this->subscriberFactory->create();
        $existing = $subscriber->loadByEmail($data['email']);
        if ($existing->getId()) {
            $subscriber = $existing;
        }

        $subscriber->setEmail($data['email']);
        $subscriber->setStoreId((int) ($data['store_id'] ?? 1));
        $subscriber->setStatus((int) ($data['subscriber_status'] ?? Subscriber::STATUS_SUBSCRIBED));
        $subscriber->setCustomerId((int) ($data['customer_id'] ?? 0));
        $subscriber->setStatusChangedAt(date('Y-m-d H:i:s'));
        $subscriber->save();

        return (int) $subscriber->getId();
    }

    public function clean(): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('newsletter_subscriber');
        $connection->delete(
            $table,
            ["subscriber_email LIKE ?" => '%@example.%']
        );
    }
}
```

**Step 2: Register in DI**

**Files:**
- Modify: `src/etc/di.xml`

Add under `EntityHandlerPool/handlers`:

```xml
<item name="newsletter_subscriber" xsi:type="object">RunAsRoot\Seeder\EntityHandler\NewsletterSubscriberHandler</item>
```

Add under `DataGeneratorPool/generators`:

```xml
<item name="newsletter_subscriber" xsi:type="object">RunAsRoot\Seeder\DataGenerator\NewsletterSubscriberDataGenerator</item>
```

**Step 3: Static checks**

Run: `composer phpstan` — expect no new errors.
Run: `composer phpcs` — expect no new warnings.

**Step 4: Commit**

```bash
git add src/EntityHandler/NewsletterSubscriberHandler.php src/etc/di.xml
git commit -m "feat(newsletter): add NewsletterSubscriberHandler + DI wiring"
```

---

## Phase 3 — Cart rule

### Task 6: Add composer dependency for `magento/module-sales-rule`

**Files:**
- Modify: `composer.json`
- Modify: `src/etc/module.xml`

Same pattern as Task 2: add to `require`, stub `repositories.magento-stubs.package`, `<sequence>` entry `<module name="Magento_SalesRule"/>`.

Commit: `chore(composer): add magento/module-sales-rule dependency`

### Task 7: Write failing unit tests for `CartRuleDataGenerator`

**Files:**
- Create: `Test/Unit/DataGenerator/CartRuleDataGeneratorTest.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\CartRuleDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class CartRuleDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_cart_rule(): void
    {
        $this->assertSame('cart_rule', (new CartRuleDataGenerator())->getType());
    }

    public function test_get_order_returns_50(): void
    {
        $this->assertSame(50, (new CartRuleDataGenerator())->getOrder());
    }

    public function test_generate_shape_contains_required_keys(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(1);
        $registry = new GeneratedDataRegistry();

        $data = (new CartRuleDataGenerator())->generate($faker, $registry);

        foreach (['name', 'is_active', 'website_ids', 'customer_group_ids', 'simple_action', 'coupon'] as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
        }
        $this->assertSame(1, $data['is_active']);
        $this->assertContains($data['simple_action'], ['by_percent', 'by_fixed', 'free_shipping']);
        $this->assertArrayHasKey('code', $data['coupon']);
        $this->assertMatchesRegularExpression('/^[A-Z]+\d{1,3}-[A-Z0-9]{6}$/', $data['coupon']['code']);
    }

    public function test_generate_action_distribution_roughly_60_30_10(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        $counts = ['by_percent' => 0, 'by_fixed' => 0, 'free_shipping' => 0];
        for ($i = 0; $i < 1000; $i++) {
            $faker->seed($i);
            $counts[$gen->generate($faker, $registry)['simple_action']]++;
        }

        $this->assertGreaterThan(500, $counts['by_percent'], 'by_percent weight ~60');
        $this->assertGreaterThan(200, $counts['by_fixed'], 'by_fixed weight ~30');
        $this->assertGreaterThan(50, $counts['free_shipping'], 'free_shipping weight ~10');
    }

    public function test_generate_free_shipping_has_zero_discount_amount(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        for ($i = 0; $i < 300; $i++) {
            $faker->seed($i);
            $data = $gen->generate($faker, $registry);
            if ($data['simple_action'] === 'free_shipping') {
                $this->assertSame(0.0, (float) $data['discount_amount']);
                return;
            }
        }
        $this->fail('No free_shipping action produced in 300 iterations');
    }

    public function test_generate_percent_amount_within_5_to_30(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $gen = new CartRuleDataGenerator();

        for ($i = 0; $i < 300; $i++) {
            $faker->seed($i);
            $data = $gen->generate($faker, $registry);
            if ($data['simple_action'] === 'by_percent') {
                $this->assertGreaterThanOrEqual(5, $data['discount_amount']);
                $this->assertLessThanOrEqual(30, $data['discount_amount']);
            }
        }
    }
}
```

Run: `vendor/bin/phpunit Test/Unit/DataGenerator/CartRuleDataGeneratorTest.php` — expect class-not-found failures.

### Task 8: Implement `CartRuleDataGenerator`

**Files:**
- Create: `src/DataGenerator/CartRuleDataGenerator.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class CartRuleDataGenerator implements DataGeneratorInterface
{
    private const ACTION_WEIGHTS = [
        'by_percent' => 60,
        'by_fixed' => 30,
        'free_shipping' => 10,
    ];

    private const CODE_PREFIXES = ['SAVE', 'DEAL', 'PROMO', 'BONUS'];

    public function getType(): string
    {
        return 'cart_rule';
    }

    public function getOrder(): int
    {
        return 50;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $action = $this->weightedPick($faker, self::ACTION_WEIGHTS);
        [$amount, $prefix] = match ($action) {
            'by_percent'    => [(float) $faker->numberBetween(5, 30), 'SAVE'],
            'by_fixed'      => [(float) $faker->numberBetween(5, 50), 'DEAL'],
            'free_shipping' => [0.0, 'PROMO'],
        };

        $ruleName = sprintf('Seed Rule — %s', $faker->words(2, true));
        $code = sprintf(
            '%s%d-%s',
            $prefix,
            (int) $amount,
            strtoupper($faker->bothify('??####'))
        );

        return [
            'name' => $ruleName,
            'description' => $faker->sentence(),
            'is_active' => 1,
            'website_ids' => [1],
            'customer_group_ids' => [0, 1, 2, 3],
            'from_date' => null,
            'to_date' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'),
            'uses_per_customer' => 0,
            'simple_action' => $action,
            'discount_amount' => $amount,
            'discount_qty' => 0,
            'stop_rules_processing' => 0,
            'sort_order' => 0,
            'coupon' => [
                'type' => 'specific_coupon',
                'code' => $code,
                'uses_per_coupon' => 0,
            ],
        ];
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return 0;
    }

    private function weightedPick(Generator $faker, array $weights): string
    {
        $total = array_sum($weights);
        $roll = $faker->numberBetween(1, $total);
        $acc = 0;
        foreach ($weights as $key => $weight) {
            $acc += $weight;
            if ($roll <= $acc) {
                return (string) $key;
            }
        }
        return (string) array_key_first($weights);
    }
}
```

Run tests: `vendor/bin/phpunit Test/Unit/DataGenerator/CartRuleDataGeneratorTest.php` — expect all green.

Commit: `feat(cart-rule): add CartRuleDataGenerator`

### Task 9: Implement `CartRuleHandler`

**Files:**
- Create: `src/EntityHandler/CartRuleHandler.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\SalesRule\Api\Data\RuleInterfaceFactory;
use Magento\SalesRule\Api\Data\CouponInterfaceFactory;
use Magento\SalesRule\Api\CouponRepositoryInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\SalesRule\Model\Rule;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class CartRuleHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly RuleInterfaceFactory $ruleFactory,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly CouponInterfaceFactory $couponFactory,
        private readonly CouponRepositoryInterface $couponRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    public function getType(): string
    {
        return 'cart_rule';
    }

    public function create(array $data): int
    {
        $rule = $this->ruleFactory->create();
        $rule->setName($data['name']);
        $rule->setDescription($data['description'] ?? '');
        $rule->setIsActive((bool) ($data['is_active'] ?? 1));
        $rule->setWebsiteIds($data['website_ids'] ?? [1]);
        $rule->setCustomerGroupIds($data['customer_group_ids'] ?? [0, 1, 2, 3]);
        $rule->setFromDate($data['from_date'] ?? null);
        $rule->setStopRulesProcessing((bool) ($data['stop_rules_processing'] ?? 0));
        $rule->setUsesPerCustomer((int) ($data['uses_per_customer'] ?? 0));
        $rule->setSimpleAction($data['simple_action']);
        $rule->setDiscountAmount((float) $data['discount_amount']);
        $rule->setDiscountQty((int) ($data['discount_qty'] ?? 0));
        $rule->setSortOrder((int) ($data['sort_order'] ?? 0));

        if (!empty($data['to_date'])) {
            $rule->setToDate($data['to_date']);
        }

        if ($data['simple_action'] === 'free_shipping') {
            $rule->setSimpleFreeShipping(Rule::FREE_SHIPPING_ITEM);
        }

        $couponType = ($data['coupon']['type'] ?? null) === 'specific_coupon'
            ? Rule::COUPON_TYPE_SPECIFIC
            : Rule::COUPON_TYPE_NO_COUPON;
        $rule->setCouponType($couponType);

        $saved = $this->ruleRepository->save($rule);

        if ($couponType === Rule::COUPON_TYPE_SPECIFIC && !empty($data['coupon']['code'])) {
            $this->createCouponForRule((int) $saved->getRuleId(), $data['coupon']);
        }

        return (int) $saved->getRuleId();
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('name', 'Seed Rule — %', 'like')
            ->create();

        foreach ($this->ruleRepository->getList($searchCriteria)->getItems() as $rule) {
            $this->ruleRepository->deleteById($rule->getRuleId());
        }
    }

    private function createCouponForRule(int $ruleId, array $couponData): void
    {
        $attempts = 0;
        do {
            $coupon = $this->couponFactory->create();
            $coupon->setRuleId($ruleId);
            $coupon->setCode($couponData['code']);
            $coupon->setType(\Magento\SalesRule\Helper\Coupon::COUPON_TYPE_SPECIFIC_AUTOGENERATED);
            $coupon->setUsageLimit((int) ($couponData['uses_per_coupon'] ?? 0));
            try {
                $this->couponRepository->save($coupon);
                return;
            } catch (\Magento\Framework\Exception\AlreadyExistsException $e) {
                $couponData['code'] = $couponData['code'] . '-' . strtoupper(bin2hex(random_bytes(2)));
                $attempts++;
            }
        } while ($attempts < 3);

        throw new \RuntimeException('Could not create unique coupon code after 3 retries');
    }
}
```

**DI wiring:**

Add to `src/etc/di.xml`:

```xml
<item name="cart_rule" xsi:type="object">RunAsRoot\Seeder\EntityHandler\CartRuleHandler</item>
```
(into `EntityHandlerPool/handlers`)

```xml
<item name="cart_rule" xsi:type="object">RunAsRoot\Seeder\DataGenerator\CartRuleDataGenerator</item>
```
(into `DataGeneratorPool/generators`)

Run: `composer phpstan && composer phpcs` → clean.

Commit: `feat(cart-rule): add CartRuleHandler + DI wiring`

---

## Phase 4 — Wishlist

### Task 10: Add composer dependency for `magento/module-wishlist`

Same pattern as Task 2: `composer.json` require + stub, `<module name="Magento_Wishlist"/>` in `module.xml`.

Commit: `chore(composer): add magento/module-wishlist dependency`

### Task 11: Write failing unit tests for `WishlistDataGenerator`

**Files:**
- Create: `Test/Unit/DataGenerator/WishlistDataGeneratorTest.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\DataGenerator;

use RunAsRoot\Seeder\DataGenerator\WishlistDataGenerator;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Factory;
use PHPUnit\Framework\TestCase;

final class WishlistDataGeneratorTest extends TestCase
{
    public function test_get_type_returns_wishlist(): void
    {
        $this->assertSame('wishlist', (new WishlistDataGenerator())->getType());
    }

    public function test_get_order_returns_60(): void
    {
        $this->assertSame(60, (new WishlistDataGenerator())->getOrder());
    }

    public function test_get_dependencies_returns_customer_and_product(): void
    {
        $this->assertSame(
            ['customer', 'product'],
            (new WishlistDataGenerator())->getDependencies()
        );
    }

    public function test_get_dependency_count_customer_is_one_to_one(): void
    {
        $gen = new WishlistDataGenerator();
        $this->assertSame(10, $gen->getDependencyCount('customer', 10));
        $this->assertSame(0, $gen->getDependencyCount('product', 10));
    }

    public function test_generate_without_customer_throws(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('product', ['id' => 1, 'sku' => 'seed-1']);

        $this->expectException(\RuntimeException::class);
        (new WishlistDataGenerator())->generate($faker, $registry);
    }

    public function test_generate_without_product_throws(): void
    {
        $faker = Factory::create('en_US');
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 42, 'email' => 'a@example.com']);

        $this->expectException(\RuntimeException::class);
        (new WishlistDataGenerator())->generate($faker, $registry);
    }

    public function test_generate_shape_with_registered_entities(): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(3);
        $registry = new GeneratedDataRegistry();
        $registry->add('customer', ['id' => 42, 'email' => 'a@example.com']);
        for ($i = 1; $i <= 10; $i++) {
            $registry->add('product', ['id' => $i, 'sku' => "seed-{$i}"]);
        }

        $data = (new WishlistDataGenerator())->generate($faker, $registry);

        $this->assertSame(42, $data['customer_id']);
        $this->assertSame(0, $data['shared']);
        $this->assertIsArray($data['items']);
        $this->assertGreaterThanOrEqual(1, count($data['items']));
        $this->assertLessThanOrEqual(5, count($data['items']));
        foreach ($data['items'] as $item) {
            $this->assertArrayHasKey('product_id', $item);
            $this->assertSame(1, $item['qty']);
            $this->assertContains($item['product_id'], range(1, 10));
        }
    }
}
```

Run tests: expect class-not-found failures.

### Task 12: Implement `WishlistDataGenerator`

**Files:**
- Create: `src/DataGenerator/WishlistDataGenerator.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\DataGenerator;

use RunAsRoot\Seeder\Api\DataGeneratorInterface;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;
use Faker\Generator;

class WishlistDataGenerator implements DataGeneratorInterface
{
    public function getType(): string
    {
        return 'wishlist';
    }

    public function getOrder(): int
    {
        return 60;
    }

    public function generate(Generator $faker, GeneratedDataRegistry $registry): array
    {
        $customers = $registry->getAll('customer');
        $products = $registry->getAll('product');

        if (empty($customers)) {
            throw new \RuntimeException('wishlist requires at least one seeded customer');
        }
        if (empty($products)) {
            throw new \RuntimeException('wishlist requires at least one seeded product');
        }

        $customer = $faker->randomElement($customers);
        $itemCount = min($faker->numberBetween(1, 5), count($products));
        $picked = $faker->randomElements($products, $itemCount);

        $items = [];
        foreach ($picked as $product) {
            $items[] = [
                'product_id' => (int) $product['id'],
                'qty' => 1,
            ];
        }

        return [
            'customer_id' => (int) $customer['id'],
            'shared' => 0,
            'items' => $items,
        ];
    }

    public function getDependencies(): array
    {
        return ['customer', 'product'];
    }

    public function getDependencyCount(string $dependencyType, int $selfCount): int
    {
        return $dependencyType === 'customer' ? $selfCount : 0;
    }
}
```

Run tests → green.

Commit: `feat(wishlist): add WishlistDataGenerator`

### Task 13: Implement `WishlistHandler`

**Files:**
- Create: `src/EntityHandler/WishlistHandler.php`

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Wishlist\Model\WishlistFactory;
use RunAsRoot\Seeder\Api\EntityHandlerInterface;

class WishlistHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly WishlistFactory $wishlistFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ResourceConnection $resource,
    ) {
    }

    public function getType(): string
    {
        return 'wishlist';
    }

    public function create(array $data): int
    {
        $wishlist = $this->wishlistFactory->create();
        $wishlist->loadByCustomerId((int) $data['customer_id'], true);
        $wishlist->setShared((int) ($data['shared'] ?? 0));

        foreach ($data['items'] as $itemData) {
            $product = $this->productRepository->getById((int) $itemData['product_id']);
            $wishlist->addNewItem(
                $product,
                new DataObject(['qty' => (int) ($itemData['qty'] ?? 1)])
            );
        }

        $wishlist->save();

        return (int) $wishlist->getId();
    }

    public function clean(): void
    {
        $connection = $this->resource->getConnection();
        $wishlistTable = $this->resource->getTableName('wishlist');
        $customerTable = $this->resource->getTableName('customer_entity');

        $select = $connection->select()
            ->from($customerTable, ['entity_id'])
            ->where('email LIKE ?', '%@example.%');
        $customerIds = $connection->fetchCol($select);

        if (!empty($customerIds)) {
            $connection->delete($wishlistTable, ['customer_id IN (?)' => $customerIds]);
        }
    }
}
```

Note: `wishlist_item` rows cascade on `wishlist.customer_id` FK — Magento schema.

**DI wiring:**

```xml
<item name="wishlist" xsi:type="object">RunAsRoot\Seeder\EntityHandler\WishlistHandler</item>
```
(into `EntityHandlerPool/handlers`)

```xml
<item name="wishlist" xsi:type="object">RunAsRoot\Seeder\DataGenerator\WishlistDataGenerator</item>
```
(into `DataGeneratorPool/generators`)

Run: `composer phpstan && composer phpcs` → clean.

Commit: `feat(wishlist): add WishlistHandler + DI wiring`

---

## Phase 5 — Integration smoke test + verification

### Task 14: Add integration smoke test for the three new types

**Files:**
- Create: `Test/Integration/NewEntityTypesSmokeTest.php`

Mirror the existing `SeederFacadeSmokeTest` structure. Skeleton:

```php
<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Service\GenerateRunConfig;
use RunAsRoot\Seeder\Service\GenerateRunner;

/**
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
final class NewEntityTypesSmokeTest extends TestCase
{
    public function test_generate_cart_rules_creates_rules_and_coupons(): void
    {
        $om = Bootstrap::getObjectManager();
        $runner = $om->create(GenerateRunner::class);
        $config = new GenerateRunConfig(counts: ['cart_rule' => 3], locale: 'en_US', seed: 42, fresh: false);

        $results = $runner->run($config);

        $this->assertSame(3, $results[0]['count']);
        $this->assertSame(0, $results[0]['failed']);

        $connection = $om->get(ResourceConnection::class)->getConnection();
        $ruleTable = $connection->getTableName('salesrule');
        $couponTable = $connection->getTableName('salesrule_coupon');

        $rules = $connection->fetchAll("SELECT * FROM {$ruleTable} WHERE name LIKE 'Seed Rule — %'");
        $this->assertCount(3, $rules);

        $coupons = $connection->fetchAll("SELECT * FROM {$couponTable} WHERE code LIKE 'SAVE%' OR code LIKE 'DEAL%' OR code LIKE 'PROMO%' OR code LIKE 'BONUS%'");
        $this->assertGreaterThanOrEqual(3, count($coupons));
    }

    public function test_generate_newsletter_subscribers_with_customers(): void
    {
        $om = Bootstrap::getObjectManager();
        $runner = $om->create(GenerateRunner::class);
        $config = new GenerateRunConfig(
            counts: ['customer' => 5, 'newsletter_subscriber' => 10],
            locale: 'en_US',
            seed: 1,
            fresh: false,
        );

        $results = $runner->run($config);

        $connection = $om->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('newsletter_subscriber');
        $rows = $connection->fetchAll("SELECT * FROM {$table} WHERE subscriber_email LIKE '%@example.%'");
        $this->assertGreaterThanOrEqual(10, count($rows));
    }

    public function test_generate_wishlists_with_customers_and_products(): void
    {
        $om = Bootstrap::getObjectManager();
        $runner = $om->create(GenerateRunner::class);
        $config = new GenerateRunConfig(
            counts: ['customer' => 3, 'product' => 5, 'wishlist' => 3],
            locale: 'en_US',
            seed: 7,
            fresh: false,
        );

        $results = $runner->run($config);

        $wishlistResult = null;
        foreach ($results as $r) {
            if ($r['type'] === 'wishlist') {
                $wishlistResult = $r;
                break;
            }
        }
        $this->assertNotNull($wishlistResult);
        $this->assertSame(3, $wishlistResult['count']);

        $connection = $om->get(ResourceConnection::class)->getConnection();
        $wishlistTable = $connection->getTableName('wishlist');
        $itemTable = $connection->getTableName('wishlist_item');
        $wishlists = $connection->fetchAll("SELECT * FROM {$wishlistTable}");
        $this->assertGreaterThanOrEqual(3, count($wishlists));
        $items = $connection->fetchAll("SELECT * FROM {$itemTable}");
        $this->assertGreaterThanOrEqual(3, count($items));
    }
}
```

### Task 15: Run the integration suite against Mage-OS via Warden

Per `~/.claude/projects/-Users-david-Herd-seeder/memory/reference_mage_os_env.md`, use the `mage-os-typesense` Warden env. The seeder source must be **copied** (not symlinked) into `app/code/RunAsRoot/Seeder/` because symlinks break in that env (`reference_warden_modules.md`).

**Step 1:** Copy module into `mage-os-typesense/app/code/RunAsRoot/Seeder/` (plain `cp -r`).
**Step 2:** `warden env exec -T php-fpm bin/magento setup:upgrade`
**Step 3:** `warden env exec -T php-fpm bin/magento setup:di:compile`
**Step 4:** `warden env exec -T php-fpm vendor/bin/phpunit --filter 'NewEntityTypesSmokeTest' dev/tests/integration` (adjust path for the env's integration test runner).
**Step 5:** If any test fails, debug via systematic-debugging skill; **do not** proceed to Phase 6 until green.

### Task 16: Clean idempotency smoke

**Step 1:** Run integration suite once → green.
**Step 2:** Re-run the same suite (with `@magentoDbIsolation` reinitializing fixtures) — confirm no unique-key duplicates in `salesrule_coupon`, `newsletter_subscriber`, `wishlist`.
**Step 3:** If duplicates appear (usually from cart rule coupon code clashes), investigate — the retry loop in `CartRuleHandler::createCouponForRule` should handle it, but if it's triggered repeatedly we may need to raise the attempt budget.

### Task 17: Commit integration test

```bash
git add Test/Integration/NewEntityTypesSmokeTest.php
git commit -m "test(integration): smoke cart_rule + wishlist + newsletter_subscriber"
```

---

## Phase 6 — Finalization

### Task 18: Update README if present

**Files:**
- Check: `README.md` — if the supported type list is documented, add `cart_rule`, `wishlist`, `newsletter_subscriber` and note the new composer deps.

If README doesn't list types: skip this task.

Commit (if applicable): `docs(readme): document new entity types`

### Task 19: Final static analysis + full test run

```bash
composer phpcs
composer phpstan
vendor/bin/phpunit
```

All three must be green. Do not proceed past this step while anything is red.

### Task 20: Summarize for user

Report: what was added, what the commit range is (`git log --oneline main..HEAD` if on a branch, else `git log --oneline <last-release-tag>..HEAD`), any open items / known limitations from the design doc's "Open follow-ups".

**Do NOT push to origin** — the user pushes manually on this repo.

---

## Skill references

- `@superpowers:test-driven-development` — follow red/green/refactor per task
- `@superpowers:verification-before-completion` — never claim "done" without running commands
- `@superpowers:systematic-debugging` — if integration smoke fails
- `@superpowers:executing-plans` — required sub-skill for this plan
