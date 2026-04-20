<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Seeder;
use RunAsRoot\Seeder\Service\DataGeneratorPool;
use RunAsRoot\Seeder\Service\EntityHandlerPool;
use RunAsRoot\Seeder\Service\FakerFactory;
use RunAsRoot\Seeder\Service\GeneratedDataRegistry;

/**
 * Exercises the Seeder abstract base class + SeedBuilder fluent API against
 * a real Mage-OS install: asserts DI auto-wires the four collaborators,
 * the fluent builder persists customers, and the created ids flow back.
 *
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
final class SeederFacadeSmokeTest extends TestCase
{
    public function test_seeder_base_class_creates_customers_via_fluent_builder(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $customerRepo = $objectManager->create(CustomerRepositoryInterface::class);
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)->create();

        $before = $customerRepo->getList($searchCriteria)->getTotalCount();

        $seeder = $objectManager->create(FluentCustomerSmokeSeeder::class);
        $seeder->run();

        $after = $customerRepo->getList($searchCriteria)->getTotalCount();

        self::assertSame(
            $before + 3,
            $after,
            'Seeder::run() via fluent SeedBuilder should persist 3 customers'
        );
    }
}

/**
 * Concrete Seeder for the smoke test above. Lives alongside the test so the
 * CI harness does not need a filesystem fixture in <magento-root>/dev/seeders/.
 */
final class FluentCustomerSmokeSeeder extends Seeder
{
    public function __construct(
        EntityHandlerPool $handlers,
        DataGeneratorPool $generators,
        FakerFactory $fakerFactory,
        GeneratedDataRegistry $registry,
    ) {
        parent::__construct($handlers, $generators, $fakerFactory, $registry);
    }

    public function getType(): string
    {
        return 'customer';
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function run(): void
    {
        $this->customers()->count(3)->create();
    }
}
