<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Integration\Console;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\Console\Command\SeedCommand;
use RunAsRoot\Seeder\Console\Command\SeedMakeCommand;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedMakeRoundtripTest extends TestCase
{
    private ObjectManagerInterface $om;

    protected function setUp(): void
    {
        $this->om = Bootstrap::getObjectManager();
    }

    public function test_scaffolded_order_seeder_runs_and_creates_orders(): void
    {
        $make = $this->om->get(SeedMakeCommand::class);
        (new CommandTester($make))->execute(
            [
                '--type' => 'order',
                '--count' => '3',
                '--format' => 'php',
                '--name' => 'SmokeOrderSeeder',
                '--force' => true,
            ],
            ['interactive' => false],
        );

        $seed = $this->om->get(SeedCommand::class);
        $exit = (new CommandTester($seed))->execute(['--only' => 'order']);

        $this->assertSame(0, $exit);

        $searchCriteria = $this->om->get(SearchCriteriaBuilder::class)->create();
        $orders = $this->om->get(OrderRepositoryInterface::class)->getList($searchCriteria)->getItems();

        $this->assertGreaterThanOrEqual(3, count($orders));
    }
}
