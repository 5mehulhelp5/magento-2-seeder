<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Service;

use DavidLambauer\Seeder\Api\SeederInterface;

class ArraySeederAdapter implements SeederInterface
{
    private const DEFAULT_ORDER = [
        'category' => 10,
        'product' => 20,
        'customer' => 30,
        'order' => 40,
        'cms' => 50,
    ];

    public function __construct(
        private readonly array $config,
        private readonly EntityHandlerPool $handlerPool,
    ) {
    }

    public function getType(): string
    {
        return $this->config['type'];
    }

    public function getOrder(): int
    {
        return $this->config['order'] ?? self::DEFAULT_ORDER[$this->config['type']] ?? 100;
    }

    public function run(): void
    {
        $handler = $this->handlerPool->get($this->config['type']);

        foreach ($this->config['data'] as $item) {
            $handler->create($item);
        }
    }
}
