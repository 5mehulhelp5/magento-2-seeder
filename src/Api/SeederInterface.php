<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\Api;

interface SeederInterface
{
    public function getType(): string;

    public function getOrder(): int;

    public function run(): void;
}
