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
