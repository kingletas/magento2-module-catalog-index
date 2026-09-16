<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;

class CategoryAttributeCodesTest extends TestCase
{
    public function testOnlySelectedTableBackedCategoryAttributesAreSkipped(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->with(Category::ENTITY)->willReturn([
            'name' => $this->attribute(false),
            'is_active' => $this->attribute(false),
            'path' => $this->attribute(true),
            'not_selected' => $this->attribute(false),
        ]);
        $collection = $this->createMock(Collection::class);
        $collection->method('isAttributeAdded')->willReturnCallback(
            static fn (string $code): bool => $code !== 'not_selected'
        );

        $codes = (new CategoryAttributeCodes($eavConfig))->selectedOn($collection);

        $this->assertSame(['name', 'is_active'], $codes);
    }

    /**
     * A category with no products carries a zero, so null has to mean the document never said.
     */
    public function testACategoryDocumentAnswersItsOwnProductCount(): void
    {
        $view = new CategoryView(12, ['attributes' => ['name' => 'Jackets'], 'product_count' => 28]);

        $this->assertSame(28, $view->productCount());
        $this->assertSame(0, (new CategoryView(12, ['product_count' => 0]))->productCount());
        $this->assertNull((new CategoryView(12, []))->productCount());
    }

    private function attribute(bool $static): AbstractAttribute
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('isStatic')->willReturn($static);

        return $attribute;
    }
}
