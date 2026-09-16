<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\VariantFieldProvider;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

class VariantFieldProviderTest extends FieldProviderTestCase
{
    private int $childLoads = 0;

    public function testAConfigurableCarriesItsVariantsAndSuperAttributes(): void
    {
        $this->answers['catalog_product_super_link'] = [
            ['parent_id' => '5', 'product_id' => '51'],
            ['parent_id' => '5', 'product_id' => '52'],
        ];
        // Both queries start from this table, and only the option query joins.
        $this->answers['catalog_product_super_attribute'] = static function (array $query): array {
            if (!in_array('joinInner', array_column($query['calls'], 0), true)) {
                return [[
                    'product_id' => '5',
                    'attribute_id' => '93',
                    'position' => '0',
                    'product_super_attribute_id' => '11',
                    'label' => 'Colour',
                    'use_default' => '0',
                ]];
            }

            return [
                [
                    'parent_link_id' => '5',
                    'attribute_id' => '93',
                    'sku' => 'SKU-5-RED',
                    'product_id' => '5',
                    'attribute_code' => 'color',
                    'value_index' => '49',
                    'super_attribute_label' => 'Color',
                    'option_title' => 'Red',
                    'default_title' => 'Red',
                ],
            ];
        };

        $drafts = $this->runProvider($this->provider(), [
            $this->product(['entity_id' => 5, 'type_id' => 'configurable']),
            $this->product(['entity_id' => 6, 'type_id' => 'simple']),
        ]);

        $this->assertSame(1, $this->childLoads);
        $this->assertSame(
            [[
                'attribute_id' => 93,
                'code' => 'color',
                'position' => 0,
                'super_attribute_id' => 11,
                'label' => 'Colour',
                'use_default' => 0,
            ]],
            $drafts[5]->get('super_attributes')
        );
        $this->assertSame([51, 52], array_column($drafts[5]->get('variants'), 'entity_id'));
        $this->assertSame('49', $drafts[5]->get('variants')[0]['attributes']['color']);
        $this->assertFalse($drafts[6]->has('variants'));
        $this->assertSame(
            [
                [
                    'sku' => 'SKU-5-RED',
                    'product_id' => '5',
                    'attribute_code' => 'color',
                    'value_index' => '49',
                    'super_attribute_label' => 'Color',
                    'option_title' => 'Red',
                    'default_title' => 'Red',
                ],
            ],
            $drafts[5]->get('configurable_options')['93']
        );
    }

    public function testABatchWithoutConfigurablesQueriesNothing(): void
    {
        $this->runProvider($this->provider(), [$this->product(['entity_id' => 6, 'type_id' => 'simple'])]);

        $this->assertSame([], $this->queries);
        $this->assertSame(0, $this->childLoads);
    }

    private function provider(): VariantFieldProvider
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getAttributeCode')->willReturn('color');
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $eav = $this->createMock(EavConfig::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturnCallback(function (): array {
            $this->childLoads++;

            return [
                $this->product(
                    ['entity_id' => 51, 'sku' => 'SKU-5-RED', 'type_id' => 'simple', 'color' => '49', 'name' => 'Red']
                ),
                $this->product(
                    ['entity_id' => 52, 'sku' => 'SKU-5-BLUE', 'type_id' => 'simple', 'color' => '50', 'name' => 'Blue']
                ),
            ];
        });
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new VariantFieldProvider($this->resourceConnection(), $this->linkField(), $eav, $factory, ['name']);
    }
}
