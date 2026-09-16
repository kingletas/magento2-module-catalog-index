<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;

/**
 * Real products and categories holding data, without the object graph their constructors need.
 */
trait ProductDoubles
{
    /**
     * @param array<string, mixed> $data
     */
    protected function product(array $data): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setOrigData'])
            ->getMock();
        $product->method('getId')->willReturnCallback(static fn (): mixed => $product->getData('entity_id'));
        $product->method('setOrigData')->willReturnSelf();
        $product->setData($data);

        return $product;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function category(array $data): Category
    {
        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setOrigData', 'setStoreId'])
            ->getMock();
        $category->method('setStoreId')->willReturnCallback(
            static fn (mixed $storeId): Category => $category->setData('store_id', $storeId)
        );
        $category->method('getId')->willReturnCallback(static fn (): mixed => $category->getData('entity_id'));
        $category->method('setOrigData')->willReturnSelf();
        $category->setData($data);

        return $category;
    }
}
