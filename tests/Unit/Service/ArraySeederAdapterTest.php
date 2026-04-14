<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Test\Unit\Service;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use DavidLambauer\Seeder\Service\ArraySeederAdapter;
use DavidLambauer\Seeder\Service\EntityHandlerPool;
use PHPUnit\Framework\TestCase;

final class ArraySeederAdapterTest extends TestCase
{
    public function test_get_type_returns_configured_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $this->assertSame('customer', $adapter->getType());
    }

    public function test_get_order_returns_default_for_known_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $this->assertSame(30, $adapter->getOrder());
    }

    public function test_get_order_returns_custom_order_when_specified(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => [], 'order' => 5],
            $pool
        );

        $this->assertSame(5, $adapter->getOrder());
    }

    public function test_get_order_returns_100_for_unknown_type(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $pool = new EntityHandlerPool(['custom' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'custom', 'data' => []],
            $pool
        );

        $this->assertSame(100, $adapter->getOrder());
    }

    public function test_run_calls_create_for_each_data_item(): void
    {
        $expectedData = [
            ['email' => 'john@test.com'],
            ['email' => 'jane@test.com'],
        ];

        $handler = $this->createMock(EntityHandlerInterface::class);
        $callCount = 0;
        $handler->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (array $data) use (&$callCount, $expectedData): void {
                $this->assertSame($expectedData[$callCount], $data);
                $callCount++;
            });

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => $expectedData],
            $pool
        );

        $adapter->run();
    }

    public function test_run_does_nothing_with_empty_data(): void
    {
        $handler = $this->createMock(EntityHandlerInterface::class);
        $handler->expects($this->never())->method('create');

        $pool = new EntityHandlerPool(['customer' => $handler]);

        $adapter = new ArraySeederAdapter(
            ['type' => 'customer', 'data' => []],
            $pool
        );

        $adapter->run();
    }
}
