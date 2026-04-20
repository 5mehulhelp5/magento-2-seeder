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
