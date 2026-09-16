<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\CategoryDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\CategoryProductCounts;
use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use PHPUnit\Framework\TestCase;

class CategoryDocumentBuilderTest extends TestCase
{
    use ProductDoubles;
    use StubbedDatabase;

    /**
     * A category under another store's root, or switched off, has no page here.
     */
    public function testOnlyActiveCategoriesUnderTheStoresRootAreBuilt(): void
    {
        $this->answers['url_rewrite'] = ['12' => 'jackets.html'];
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn([
            $this->category(
                ['entity_id' => 12, 'path' => '1/2/12', 'is_active' => 1, 'name' => 'Jackets', 'children' => ['x']]
            ),
            $this->category(['entity_id' => 13, 'path' => '1/2/13', 'is_active' => 0]),
            $this->category(['entity_id' => 14, 'path' => '1/9/14', 'is_active' => 1]),
        ]);
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $counts = $this->createMock(CategoryProductCounts::class);
        $counts->method('productCount')->willReturn(28);

        $batch = (new CategoryDocumentBuilder(
            $factory,
            $this->resourceConnection(),
            new FingerprintCalculator(),
            $counts
        ))->build([12, 13, 14, 15], 1, 2, 5);

        $this->assertSame(['12'], $batch->documentIds());
        $this->assertSame('jackets.html', $batch->documents[0]->get('request_path'));
        $this->assertSame('Jackets', $batch->documents[0]->get('attributes')['name']);
        $this->assertArrayNotHasKey('children', $batch->documents[0]->get('attributes'));
        $this->assertSame([13, 14, 15], array_values($batch->removedIds));
        $this->assertSame(28, $batch->documents[0]->get('product_count'));
    }
}
