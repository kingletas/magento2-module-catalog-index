<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\ConfigurableFieldProvider;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

class ConfigurableFieldProviderTest extends FieldProviderTestCase
{
    public function testAConfigurableCarriesItsSuperAttributesAndOptionRows(): void
    {
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
        $this->assertFalse($drafts[5]->has('variants'));
        $this->assertFalse($drafts[6]->has('super_attributes'));
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
    }

    private function provider(): ConfigurableFieldProvider
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getAttributeCode')->willReturn('color');
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $eav = $this->createMock(EavConfig::class);
        $eav->method('getAttribute')->willReturn($attribute);
        return new ConfigurableFieldProvider($this->resourceConnection(), $this->linkField(), $eav);
    }
}
