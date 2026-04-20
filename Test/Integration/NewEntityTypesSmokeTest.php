<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration;

use Magento\Framework\App\ResourceConnection;
use Magento\SalesRule\Api\Data\CouponInterface;
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

        $coupons = $connection->fetchAll(
            "SELECT * FROM {$couponTable} "
            . "WHERE code LIKE 'SAVE%' OR code LIKE 'DEAL%' OR code LIKE 'PROMO%'"
        );
        $this->assertGreaterThanOrEqual(3, count($coupons));

        foreach ($coupons as $coupon) {
            $this->assertSame(
                (int) CouponInterface::TYPE_MANUAL,
                (int) $coupon['type'],
                'Seeded coupons must be TYPE_MANUAL, not auto-generated'
            );
        }
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

        $runner->run($config);

        $connection = $om->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('newsletter_subscriber');
        $rows = $connection->fetchAll("SELECT * FROM {$table} WHERE subscriber_email LIKE '%@example.%'");
        $this->assertGreaterThanOrEqual(10, count($rows));

        $linked = array_values(array_filter($rows, fn($r) => (int) $r['customer_id'] > 0));
        $guests = array_values(array_filter($rows, fn($r) => (int) $r['customer_id'] === 0));
        $this->assertNotEmpty($linked, 'Expected at least one customer-linked subscriber');
        $this->assertNotEmpty($guests, 'Expected at least one guest subscriber');

        $linkedCustomerIds = array_map(fn($r) => (int) $r['customer_id'], $linked);
        $this->assertSame(
            count($linkedCustomerIds),
            count(array_unique($linkedCustomerIds)),
            'Linked subscribers must reference distinct customers'
        );
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

        // Find the wishlists the runner just created (those attached to seed customers)
        $seedWishlistIds = array_map(
            fn($w) => (int) $w['wishlist_id'],
            $connection->fetchAll(
                "SELECT w.wishlist_id FROM {$wishlistTable} w "
                . "INNER JOIN {$connection->getTableName('customer_entity')} c ON c.entity_id = w.customer_id "
                . "WHERE c.email LIKE '%@example.%'"
            )
        );
        $this->assertGreaterThanOrEqual(3, count($seedWishlistIds), 'Expected >= 3 wishlists tied to seed customers');

        if (!empty($seedWishlistIds)) {
            $placeholders = implode(',', array_fill(0, count($seedWishlistIds), '?'));
            $items = $connection->fetchAll(
                "SELECT * FROM {$itemTable} WHERE wishlist_id IN ({$placeholders})",
                $seedWishlistIds
            );
            $this->assertGreaterThanOrEqual(3, count($items), 'Expected >= 3 items across seed wishlists');
            foreach ($items as $item) {
                $this->assertGreaterThanOrEqual(1, (float) $item['qty']);
            }
        }
    }
}
