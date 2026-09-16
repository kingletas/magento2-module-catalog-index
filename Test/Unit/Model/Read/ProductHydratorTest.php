<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryHydrator;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductHydratorTest extends TestCase
{
    use LinkFieldDouble;
    use ProductDoubles;
    use ShippedConfig;

    private ServedOptions $served;

    private ServedAttributes $attributes;

    protected function setUp(): void
    {
        $this->served = new ServedOptions();
        $this->attributes = new ServedAttributes();
    }

    /**
     * Prices from the collection's own join stay, and salable is left for isSalable() so its events still fire.
     */
    public function testAListingItemKeepsWhatTheCollectionLoaded(): void
    {
        $item = $this->product(['entity_id' => 5, 'price' => '19.00']);

        $this->hydrator()->fillListingItem($item, $this->view(), 1);

        $this->assertSame('19.00', $item->getData('price'));
        $this->assertSame('Trail Jacket', $item->getData('name'));
        $this->assertSame('trail-jacket.html', $item->getData('request_path'));
        $this->assertSame(0, $item->getData('reviews_count'));
        $this->assertSame(1, $item->getData('is_salable'));
        $this->assertFalse($item->hasData('salable'));
        $this->assertFalse($item->hasData('_cache_instance_products'));
    }

    /**
     * Magento asks the database for a product's categories one product at a time when this is not set.
     */
    public function testTheCategoryListComesFromTheDocument(): void
    {
        $item = $this->product(['entity_id' => 5]);

        $this->hydrator()->fillListingItem($item, $this->view(), 1);

        $this->assertSame([12, 13], $item->getData('category_ids'));
    }

    public function testAProductWhoseDocumentCarriesNoCategoryListIsLeftToMagento(): void
    {
        $item = $this->product(['entity_id' => 5]);

        $this->hydrator()->fillListingItem(
            $item,
            new ProductView(5, ['type_id' => 'simple']),
            1
        );

        $this->assertFalse($item->hasData('category_ids'));
    }

    /**
     * Swatch attributes are built when Magento asks for them, so a surface that never asks pays for nothing.
     */
    public function testConfigurableAttributesAreRememberedRatherThanBuilt(): void
    {
        $item = $this->product(['entity_id' => 5]);

        $this->hydrator(['read/configurable_attributes' => '1'])
            ->fillListingItem($item, $this->view(), 1);

        $this->assertNull($item->getData('_cache_instance_configurable_attributes'));
        $this->assertNotNull($this->attributes->forProduct(5));
    }

    /**
     * Under content staging one product's row id can equal another product's entity id once the two counters drift.
     */
    public function testProductsWhoseIdsCollideKeepTheirOwnAnswers(): void
    {
        $hydrator = new ProductHydrator(
            $this->config(['read/configurable_attributes' => '1', 'read/configurable_options' => '1']),
            $this->served,
            $this->linkField('row_id'),
            $this->attributes
        );
        $older = $this->configurable(100, 93, '49');
        $newer = $this->configurable(107, 144, '170');

        $hydrator->fillListingItem($this->product(['entity_id' => 100, 'row_id' => 107]), $older, 1);
        $hydrator->fillListingItem($this->product(['entity_id' => 107, 'row_id' => 114]), $newer, 1);
        $hydrator->fillListingItem($this->product(['entity_id' => 100, 'row_id' => 107]), $older, 1);

        $this->assertSame($newer->configurable, $this->attributes->forProduct(107));
        $this->assertSame($older->configurable, $this->attributes->forProduct(100));
        $this->assertSame([['value_index' => '49']], $this->served->rows(107, 93));
        $this->assertSame([['value_index' => '170']], $this->served->rows(114, 144));
        $this->assertNull($this->served->rows(107, 144));
    }

    /**
     * The entity row Magento already read stays authoritative; the document adds what the attribute load would have.
     */
    public function testAProductPageLoadKeepsItsEntityRowAndGainsTierPrices(): void
    {
        $data = $this->hydrator()->detailData(
            $this->view(),
            ['entity_id' => 5, 'type_id' => 'configurable', 'sku' => 'SKU-5', 'price' => '30.00', 'store_id' => 3],
            3
        );

        $this->assertSame('SKU-5', $data['sku']);
        $this->assertSame('30.00', $data['price']);
        $this->assertSame('Trail Jacket', $data['name']);
        $this->assertSame([['price' => 8.0]], $data['tier_price']);
        // Variants are never built from documents: Magento caches them across requests for every other surface.
        $this->assertArrayNotHasKey('_cache_instance_products', $data);
        $this->assertSame(1, $data['is_salable']);
        $this->assertArrayNotHasKey('salable', $data);
    }

    public function testACategoryPageLoadKeepsItsEntityRowAndGainsItsAttributes(): void
    {
        $data = (new CategoryHydrator())->detailData(
            new CategoryView(12, [
                'attributes' => ['name' => 'Jackets', 'path' => 'wrong'],
                'request_path' => 'jackets.html',
            ]),
            ['entity_id' => 12, 'path' => '1/2/12']
        );

        $this->assertSame('Jackets', $data['name']);
        $this->assertSame('1/2/12', $data['path']);
        $this->assertSame('jackets.html', $data['request_path']);
    }

    /**
     * @param array<string, string> $config
     */
    private function hydrator(array $config = []): ProductHydrator
    {
        return new ProductHydrator(
            $this->config($config),
            $this->served,
            $this->linkField(),
            $this->attributes
        );
    }

    private function configurable(int $id, int $attributeId, string $valueIndex): ProductView
    {
        return new ProductView($id, [
            'type_id' => 'configurable',
            'super_attributes' => [['attribute_id' => $attributeId, 'super_attribute_id' => $id]],
            'configurable_options' => [(string) $attributeId => [['value_index' => $valueIndex]]],
        ]);
    }

    private function view(): ProductView
    {
        return new ProductView(5, [
            'type_id' => 'configurable',
            'listing_attributes' => ['name' => 'Trail Jacket', 'price' => '25.00'],
            'request_path' => 'trail-jacket.html',
            'category_ids' => [12, 13],
            'rating_summary' => 0,
            'reviews_count' => 0,
            'tier_price' => [['price' => 8.0]],
            'super_attributes' => [
                ['attribute_id' => 93, 'code' => 'color', 'position' => 0, 'super_attribute_id' => 11],
            ],
            'variants' => [
                ['entity_id' => 51, 'sku' => 'SKU-5-RED', 'type_id' => 'simple', 'attributes' => ['color' => '49']],
                ['entity_id' => 52, 'sku' => 'SKU-5-BLUE', 'type_id' => 'simple', 'attributes' => ['color' => '50']],
            ],
        ], null, ['is_salable' => true, 'children' => ['51' => true, '52' => false]]);
    }
}
