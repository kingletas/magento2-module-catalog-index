<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\AttributeFieldProvider;
use Kingletas\CatalogIndex\Model\Build\StoredAttributes;
use Magento\Catalog\Model\Config as CatalogConfig;

class AttributeFieldProviderTest extends FieldProviderTestCase
{
    public function testValuesAreSplitIntoListingAndDetailAndNonValuesAreDropped(): void
    {
        $catalogConfig = $this->createMock(CatalogConfig::class);
        $catalogConfig->method('getProductAttributes')->willReturn(['color']);
        $provider = new AttributeFieldProvider($catalogConfig, new StoredAttributes(['store_id']), ['name']);

        $draft = $this->runProvider($provider, [$this->product([
            'entity_id' => 5,
            'name' => 'Trail Jacket',
            'color' => '12',
            'description' => '<p>Warm</p>',
            'store_id' => 1,
            '_cache_instance_products' => ['x'],
            'stock_item' => new \stdClass(),
            'size_ids' => [3, 4],
            'nested' => ['a' => ['b' => 1]],
        ])])[5];

        $this->assertSame(['color' => '12', 'name' => 'Trail Jacket'], $draft->get('listing_attributes'));
        $this->assertSame(
            ['description' => '<p>Warm</p>', 'entity_id' => 5, 'size_ids' => [3, 4]],
            $draft->get('detail_attributes')
        );
    }
}
