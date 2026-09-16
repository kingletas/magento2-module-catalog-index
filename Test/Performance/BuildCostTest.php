<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Performance;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\Field\CategoryFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\CustomOptionFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\IdentityFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\MediaFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\ReviewFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\TierPriceFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\UrlFieldProvider;
use Kingletas\CatalogIndex\Model\Build\Field\VariantFieldProvider;
use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Kingletas\Foundation\Test\Support\BudgetAssertions;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * What building a batch of product documents costs the database.
 */
class BuildCostTest extends TestCase
{
    use BudgetAssertions;
    use ProductDoubles;
    use StubbedDatabase;

    private int $collectionLoads = 0;

    /**
     * The database version of this index ran queries per product; every provider here loads its batch once.
     */
    public function testABatchOfAnySizeCostsTheSameQueries(): void
    {
        $this->assertConstantCost('queries and collection loads while building a batch', function (int $products): int {
            $this->queries = [];
            $this->collectionLoads = 0;
            $this->answers['catalog_product_super_link'] = static fn (): array => [
                ['parent_id' => '1', 'product_id' => '9000'],
            ];
            $this->answers['catalog_product_super_attribute'] = static function (array $query): array {
                if (!in_array('joinInner', array_column($query['calls'], 0), true)) {
                    return [[
                        'product_id' => '1',
                        'attribute_id' => '93',
                        'position' => '0',
                        'product_super_attribute_id' => '11',
                        'label' => 'Colour',
                        'use_default' => '0',
                    ]];
                }

                return [
                    [
                        'parent_link_id' => '1',
                        'attribute_id' => '93',
                        'sku' => 'SKU-9000',
                        'product_id' => '1',
                        'attribute_code' => 'color',
                        'value_index' => '49',
                        'super_attribute_label' => 'Color',
                        'option_title' => 'Red',
                        'default_title' => 'Red',
                    ],
                ];
            };

            $this->builder($products)->build(
                range(1, $products),
                new BuildContext(1, 1, 1, new DateTimeImmutable('2026-09-15'))
            );

            return count($this->queries) + $this->collectionLoads;
        });
    }

    private function builder(int $products): ProductDocumentBuilder
    {
        $resource = $this->resourceConnection();
        $link = $this->createMock(LinkField::class);
        $link->method('product')->willReturn('entity_id');
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getId')->willReturn(90);
        $attribute->method('getAttributeCode')->willReturn('color');
        $attribute->method('getBackendTable')->willReturn('catalog_product_entity_int');
        $eav = $this->createMock(EavConfig::class);
        $eav->method('getAttribute')->willReturn($attribute);
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturnCallback(function () use ($products): array {
            $this->collectionLoads++;

            return array_map(
                fn (int $id): \Magento\Catalog\Model\Product => $this->product([
                    'entity_id' => $id,
                    'status' => 1,
                    'type_id' => $id % 2 === 1 ? 'configurable' : 'simple',
                    'sku' => 'SKU-' . $id,
                ]),
                range(1, $products)
            );
        });
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new ProductDocumentBuilder($factory, new FingerprintCalculator(), new NullLogger(), [
            'identity' => new IdentityFieldProvider(10),
            'categories' => new CategoryFieldProvider($resource),
            'url' => new UrlFieldProvider($resource),
            'media' => new MediaFieldProvider($resource, $link, $eav),
            'tier' => new TierPriceFieldProvider($resource, $link),
            'variants' => new VariantFieldProvider($resource, $link, $eav, $factory, ['name']),
            'reviews' => new ReviewFieldProvider($resource),
            'options' => new CustomOptionFieldProvider($resource, $link),
        ]);
    }
}
