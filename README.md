# Magento 2 Database Seeder

Laravel-style database seeding for Magento 2. Define simple PHP files, run `bin/magento db:seed`, populate your dev environment.

## Installation

```bash
composer require davidlambauer/module-seeder --dev
bin/magento module:enable DavidLambauer_Seeder
bin/magento setup:upgrade
```

## Quick Start

1. Create a `dev/seeders/` directory in your Magento root
2. Drop seeder files in it (copy from `examples/` to get started)
3. Run `bin/magento db:seed`

## Usage

```bash
# Run all seeders
bin/magento db:seed

# Run only specific types
bin/magento db:seed --only=customer,order

# Skip specific types
bin/magento db:seed --exclude=cms

# Wipe relevant data and re-seed
bin/magento db:seed --fresh

# Stop on first error
bin/magento db:seed --stop-on-error

# Combine flags
bin/magento db:seed --fresh --only=customer,product
```

## Seeder Formats

### Array-Based (simple)

Create a PHP file ending in `Seeder.php` that returns an array with `type` and `data`:

```php
<?php
// dev/seeders/CustomerSeeder.php
return [
    'type' => 'customer',
    'data' => [
        ['email' => 'john@test.com', 'firstname' => 'John', 'lastname' => 'Doe', 'password' => 'Test1234!'],
        ['email' => 'jane@test.com', 'firstname' => 'Jane', 'lastname' => 'Doe', 'password' => 'Test1234!'],
    ],
];
```

### Class-Based (powerful)

For complex scenarios — loops, Faker, conditional logic:

```php
<?php
// dev/seeders/MassOrderSeeder.php
use DavidLambauer\Seeder\Api\SeederInterface;
use DavidLambauer\Seeder\Service\EntityHandlerPool;

class MassOrderSeeder implements SeederInterface
{
    public function __construct(private readonly EntityHandlerPool $handlerPool) {}

    public function getType(): string { return 'order'; }
    public function getOrder(): int { return 40; }

    public function run(): void
    {
        $handler = $this->handlerPool->get('order');
        for ($i = 0; $i < 50; $i++) {
            $handler->create([
                'customer_email' => "customer{$i}@test.com",
                'items' => [['sku' => 'PRODUCT-001', 'qty' => rand(1, 5)]],
            ]);
        }
    }
}
```

## Supported Entity Types

| Type       | Key          | What it creates                     |
|------------|-------------|-------------------------------------|
| `customer` | `customer`  | Customer accounts                   |
| `category` | `category`  | Category tree nodes                 |
| `product`  | `product`   | Simple products                     |
| `order`    | `order`     | Orders via quote-to-order flow      |
| `cms`      | `cms`       | CMS pages and blocks                |

## Default Seeding Order

1. Categories (10)
2. Products (20)
3. Customers (30)
4. Orders (40)
5. CMS (50)

Override with `'order' => 5` in array seeders or `getOrder(): int` in class seeders.

## The `--fresh` Flag

When using `--fresh`, the module cleans existing data before seeding:

- **Customers**: Deletes all non-admin customers
- **Products**: Deletes all products
- **Categories**: Deletes all categories except root (ID 1) and default (ID 2)
- **Orders**: Deletes all orders (FK cascades handle invoices, shipments, etc.)
- **CMS**: Only deletes pages/blocks with the `seed-` identifier prefix

Clean runs in reverse dependency order (orders -> products -> categories -> customers -> CMS).

## Extending

Add custom entity handlers via `di.xml`:

```xml
<type name="DavidLambauer\Seeder\Service\EntityHandlerPool">
    <arguments>
        <argument name="handlers" xsi:type="array">
            <item name="custom_entity" xsi:type="object">Vendor\Module\Seeder\CustomEntityHandler</item>
        </argument>
    </arguments>
</type>
```

Your handler must implement `DavidLambauer\Seeder\Api\EntityHandlerInterface`.

## License

MIT
