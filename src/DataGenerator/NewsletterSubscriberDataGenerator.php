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
        $alreadyLinked = [];
        foreach ($registry->getAll('newsletter_subscriber') as $existing) {
            if (($existing['customer_id'] ?? 0) > 0) {
                $alreadyLinked[(int) $existing['customer_id']] = true;
            }
        }
        $availableCustomers = array_values(array_filter(
            $customers,
            fn(array $c): bool => !isset($alreadyLinked[(int) ($c['id'] ?? 0)])
        ));
        $linkToCustomer = !empty($availableCustomers) && $faker->boolean(50);

        if ($linkToCustomer) {
            $customer = $faker->randomElement($availableCustomers);

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
