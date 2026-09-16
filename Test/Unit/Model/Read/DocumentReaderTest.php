<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Model\Read\DocumentReader;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class DocumentReaderTest extends TestCase
{
    use ShippedConfig;

    public function testProductPriceAndStockComeBackInOneRoundTripForTheShoppersGroup(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_product_1', '5', ['type_id' => 'simple']);
        $store->seed(
            'kingletas_catalog_price_1',
            '5',
            ['groups' => ['0' => ['final_price' => 20.0], '2' => ['final_price' => 16.0]]]
        );
        $store->seed('kingletas_catalog_stock_1', '5', ['is_salable' => true]);

        $views = $this->reader($store)->products([5, 6], new ReadContext(PageType::CategoryListing, 1, 1, 2));

        $this->assertSame(1, $store->calls('fetch'));
        $this->assertSame([5], array_keys($views));
        $this->assertSame(['final_price' => 16.0], $views[5]->price());
        $this->assertTrue($views[5]->isSalable());
    }

    public function testAFailedReadCountsAgainstTheBreakerAndSurfaces(): void
    {
        $store = new InMemoryDocumentStore();
        $store->unreachable = true;
        $breaker = $this->createMock(CircuitBreaker::class);
        $breaker->expects($this->once())->method('recordFailure');

        $this->expectException(DocumentStoreException::class);

        (new DocumentReader($store, new IndexNamer($this->config()), $breaker))
            ->categories([12], new ReadContext(PageType::CategoryView, 1, 1, 0));
    }

    public function testCategoriesAreReadFromTheStoresIndexAndEmptyRequestsCostNothing(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_category_1', '12', ['attributes' => ['name' => 'Jackets']]);
        $reader = $this->reader($store);

        $views = $reader->categories([12], new ReadContext(PageType::CategoryView, 1, 1, 0));

        $this->assertSame(['name' => 'Jackets'], $views[12]->attributes());
        $this->assertSame([], $reader->products([], new ReadContext(PageType::CategoryView, 1, 1, 0)));
        $this->assertSame(1, $store->calls('fetch'));
    }

    /**
     * A page walks its menu, its breadcrumb and each node's children, asking for the same categories every time.
     */
    public function testACategoryAlreadyReadInThisRequestCostsNoSecondRoundTrip(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_category_1', '12', ['attributes' => ['name' => 'Jackets']]);
        $store->seed('kingletas_catalog_category_1', '18', ['attributes' => ['name' => 'Coats']]);
        $context = new ReadContext(PageType::CategoryTree, 1, 1, 0);
        $reader = $this->reader($store);

        $reader->categories([12, 18], $context);
        $again = $reader->categories([12], $context);

        $this->assertSame(['name' => 'Jackets'], $again[12]->attributes());
        $this->assertSame(1, $store->calls('fetch'));
    }

    /**
     * A category with no document must stay missing rather than being asked for again on every list.
     */
    public function testACategoryWithNoDocumentIsAskedForOnlyOnce(): void
    {
        $store = new InMemoryDocumentStore();
        $context = new ReadContext(PageType::CategoryTree, 1, 1, 0);
        $reader = $this->reader($store);

        $this->assertSame([], $reader->categories([12], $context));
        $this->assertSame([], $reader->categories([12], $context));
        $this->assertSame(1, $store->calls('fetch'));
    }

    public function testACategoryNotYetReadIsStillFetched(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_category_1', '18', ['attributes' => ['name' => 'Coats']]);
        $context = new ReadContext(PageType::CategoryTree, 1, 1, 0);
        $reader = $this->reader($store);

        $reader->categories([12], $context);
        $views = $reader->categories([12, 18], $context);

        $this->assertSame([18], array_keys($views));
        $this->assertSame(2, $store->calls('fetch'));
    }

    private function reader(InMemoryDocumentStore $store): DocumentReader
    {
        return new DocumentReader($store, new IndexNamer($this->config()), $this->createMock(CircuitBreaker::class));
    }
}
