<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Build\StoredAttributes;
use Kingletas\CatalogIndex\Model\Read\DocumentAttributeCodes;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;

class DocumentAttributeCodesTest extends TestCase
{
    public function testOnlySelectedTableBackedAttributesThatDocumentsStoreAreSkipped(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->with(Product::ENTITY)->willReturn([
            'name' => $this->attribute(false),
            'color' => $this->attribute(false),
            'sku' => $this->attribute(true),
            'quantity_and_stock_status' => $this->attribute(false),
            'media_gallery' => $this->attribute(true),
            'not_selected' => $this->attribute(false),
        ]);
        $collection = $this->createMock(Collection::class);
        $collection->method('isAttributeAdded')->willReturnCallback(
            static fn (string $code): bool => $code !== 'not_selected'
        );

        $codes = (new DocumentAttributeCodes($eavConfig, new StoredAttributes(['quantity_and_stock_status'])))
            ->selectedOn($collection);

        $this->assertSame(['name', 'color', 'media_gallery'], $codes);
    }

    public function testStoredAttributesRefuseInternalAndListedKeys(): void
    {
        $stored = new StoredAttributes(['store_id']);

        $this->assertTrue($stored->isStored('name'));
        $this->assertFalse($stored->isStored('store_id'));
        $this->assertFalse($stored->isStored('_cache_instance_products'));
        $this->assertFalse($stored->isStored(''));
    }

    private function attribute(bool $static): AbstractAttribute
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('isStatic')->willReturn($static);

        return $attribute;
    }
}
