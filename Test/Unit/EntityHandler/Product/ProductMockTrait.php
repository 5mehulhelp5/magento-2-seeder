<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Product;

use Magento\Catalog\Model\Product;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Product mock factory for tests that run against real Magento (MageCheck).
 *
 * Real \Magento\Catalog\Model\Product resolves many "setX/getX" calls via
 * DataObject::__call, so they are not declared on the class. createMock()
 * cannot mock methods that are not on the reflection surface; addMethods()
 * declares the magic ones explicitly.
 */
trait ProductMockTrait
{
    private function createProductMock(): Product&MockObject
    {
        return $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getSku', 'setSku', 'getName', 'setName',
                'getPrice', 'setPrice', 'getAttributeSetId', 'setAttributeSetId',
                'getStatus', 'setStatus', 'getVisibility', 'setVisibility',
                'getTypeId', 'setTypeId', 'getWeight', 'setWeight',
                'setCustomAttribute', 'setProductLinks',
                'setData', 'getData', 'addImageToMediaGallery',
            ])
            ->addMethods(['setStockData', 'setWebsiteIds'])
            ->getMock();
    }
}
