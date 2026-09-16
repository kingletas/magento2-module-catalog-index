<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read\Configurable;

use Kingletas\CatalogIndex\Model\Read\Configurable\AttributeCollectionBuilder;
use Kingletas\CatalogIndex\Model\Read\Configurable\SeededAttributeCollection;
use Kingletas\CatalogIndex\Model\Read\Configurable\SeededAttributeCollectionFactory;
use Kingletas\CatalogIndex\Model\Read\ConfigurableView;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\AttributeFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class AttributeCollectionBuilderTest extends TestCase
{
    /** @var array<int, Attribute> Whatever the builder seeded, in the order it seeded it. */
    private array $seeded = [];

    public function testASeededCollectionCarriesTheRowsTheDocumentHeld(): void
    {
        $collection = $this->builder()->build($this->view(), 7, 1);

        $this->assertNotNull($collection);
        $this->assertCount(2, $this->seeded);
        $this->assertSame(93, (int) $this->seeded[0]->getData('attribute_id'));
        $this->assertSame(11, (int) $this->seeded[0]->getData('product_super_attribute_id'));
        $this->assertSame('Colour in this store', $this->seeded[0]->getData('label'));
        // The swatch JSON carries these verbatim, so a number here would change what the front end compares.
        $this->assertSame('0', $this->seeded[0]->getData('position'));
        $this->assertSame('0', $this->seeded[0]->getData('use_default'));
        $this->assertSame(7, (int) $this->seeded[0]->getData('product_id'));
    }

    /**
     * Magento's own loadOptions builds this map, and the swatch renderer reads it, so its shape is a contract.
     */
    public function testEachAttributeCarriesTheOptionMapMagentoWouldHaveBuilt(): void
    {
        $this->builder()->build($this->view(), 7, 1);

        $this->assertSame([
            [
                'value_index' => '52',
                'label' => 'Blue',
                'product_super_attribute_id' => 11,
                'default_label' => 'Blue default',
                'store_label' => 'Blue default',
                'use_default_value' => true,
            ],
        ], $this->seeded[0]->getData('options'));
        $this->assertSame(['11:52'], array_keys($this->seeded[0]->getData('options_map')));
    }

    public function testAProductWithNoSuperAttributesGetsNothing(): void
    {
        $this->assertNull($this->builder()->build(new ConfigurableView([]), 7, 1));
    }

    public function testALinkIdOfZeroGetsNothing(): void
    {
        $this->assertNull($this->builder()->build($this->view(), 0, 1));
    }

    /**
     * Half a collection would make Magento skip the query and then render an incomplete set of swatches.
     */
    public function testARowWithNoSuperAttributeIdAbandonsTheWholeCollection(): void
    {
        $view = new ConfigurableView([
            'super_attributes' => [
                ['attribute_id' => 93, 'code' => 'color', 'position' => 0, 'super_attribute_id' => 11],
                ['attribute_id' => 94, 'code' => 'size', 'position' => 1],
            ],
        ]);

        $this->assertNull($this->builder()->build($view, 7, 1));
    }

    public function testAnAttributeTheStoreNoLongerHasAbandonsTheWholeCollection(): void
    {
        $this->assertNull($this->builder(false)->build($this->view(), 7, 1));
    }

    private function view(): ConfigurableView
    {
        return new ConfigurableView([
            'super_attributes' => [
                [
                    'attribute_id' => 93,
                    'code' => 'color',
                    'position' => 0,
                    'super_attribute_id' => 11,
                    'label' => 'Colour in this store',
                    'use_default' => 0,
                ],
                [
                    'attribute_id' => 94,
                    'code' => 'size',
                    'position' => 1,
                    'super_attribute_id' => 12,
                    'label' => 'Size',
                    'use_default' => 1,
                ],
            ],
        ]);
    }

    private function builder(bool $attributeExists = true): AttributeCollectionBuilder
    {
        $eavAttribute = $this->createMock(EavAttribute::class);
        $eavAttribute->method('getId')->willReturn($attributeExists ? 93 : null);

        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->willReturn($eavAttribute);

        $attributeFactory = $this->createMock(AttributeFactory::class);
        $attributeFactory->method('create')->willReturnCallback(fn (): Attribute => $this->attribute());

        $resource = $this->createMock(ConfigurableResource::class);
        $resource->method('getAttributeOptions')->willReturn([
            ['value_index' => '52', 'option_title' => 'Blue', 'default_title' => 'Blue default'],
        ]);

        $collection = $this->createMock(SeededAttributeCollection::class);
        $collection->method('seed')->willReturnCallback(
            function (array $attributes): void {
                $this->seeded = array_values($attributes);
            }
        );

        $collectionFactory = $this->createMock(SeededAttributeCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new AttributeCollectionBuilder($collectionFactory, $attributeFactory, $eavConfig, $resource);
    }

    /**
     * The builder only ever sets data on the model and reads it back, so a data object standing behind it is enough.
     */
    private function attribute(): Attribute
    {
        $attribute = $this->createMock(Attribute::class);
        $data = new DataObject();
        $attribute->method('setData')->willReturnCallback(
            function (array|string $key, mixed $value = null) use ($data, $attribute): Attribute {
                $data->setData($key, $value);

                return $attribute;
            }
        );
        $attribute->method('getData')->willReturnCallback(
            fn (string $key = '', mixed $index = null): mixed => $data->getData($key, $index)
        );

        return $attribute;
    }
}
