<?php

declare(strict_types=1);

namespace DavidLambauer\Seeder\EntityHandler;

use DavidLambauer\Seeder\Api\EntityHandlerInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class ProductHandler implements EntityHandlerInterface
{
    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StockRegistryInterface $stockRegistry,
    ) {
    }

    public function getType(): string
    {
        return 'product';
    }

    public function create(array $data): void
    {
        $product = $this->productFactory->create();
        $product->setSku($data['sku'])
            ->setName($data['name'])
            ->setPrice($data['price'])
            ->setAttributeSetId($data['attribute_set_id'] ?? 4)
            ->setStatus($data['status'] ?? Status::STATUS_ENABLED)
            ->setVisibility($data['visibility'] ?? Visibility::VISIBILITY_BOTH)
            ->setTypeId($data['type_id'] ?? Type::TYPE_SIMPLE)
            ->setWeight($data['weight'] ?? 1.0);

        if (isset($data['description'])) {
            $product->setCustomAttribute('description', $data['description']);
        }

        if (isset($data['short_description'])) {
            $product->setCustomAttribute('short_description', $data['short_description']);
        }

        if (isset($data['url_key'])) {
            $product->setCustomAttribute('url_key', $data['url_key']);
        }

        if (isset($data['category_ids'])) {
            $product->setCustomAttribute('category_ids', $data['category_ids']);
        }

        $this->productRepository->save($product);

        $stockItem = $this->stockRegistry->getStockItemBySku($data['sku']);
        $stockItem->setQty($data['qty'] ?? 100);
        $stockItem->setIsInStock(true);
        $this->stockRegistry->updateStockItemBySku($data['sku'], $stockItem);
    }

    public function clean(): void
    {
        $searchCriteria = $this->searchCriteriaBuilder->setPageSize(10000)->create();
        $products = $this->productRepository->getList($searchCriteria);

        foreach ($products->getItems() as $product) {
            $this->productRepository->deleteById($product->getSku());
        }
    }
}
