<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Performance;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Cache\TagDispatcher;
use Kingletas\CatalogIndex\Model\Store\DocumentSchema;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Model\Read\CollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Kingletas\CatalogIndex\Model\Read\DocumentReader;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\Foundation\Test\Support\ArrayCache;
use Kingletas\Foundation\Test\Support\BudgetAssertions;
use Kingletas\Foundation\Test\Support\FakeClock;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * What a storefront page costs when it reads documents, and what a purge costs the cache.
 */
class ReadCostTest extends TestCase
{
    use LinkFieldDouble;

    use BudgetAssertions;
    use ProductDoubles;
    use ShippedConfig;

    /**
     * Product, price and stock for a whole listing come back in one request, whatever the page size.
     */
    public function testAListingOfAnySizeIsOneDocumentStoreRequest(): void
    {
        $this->assertConstantCost('document store requests for one listing', function (int $products): int {
            $store = new InMemoryDocumentStore();
            $items = [];

            foreach (range(1, $products) as $id) {
                $store->seed(
                    'kingletas_catalog_product_1',
                    (string) $id,
                    ['listing_attributes' => ['name' => 'P' . $id]]
                );
                $items[] = $this->product(['entity_id' => $id]);
            }

            $this->hydrator($store)->hydrate($this->collection($items), PageType::CategoryListing);

            return $store->calls('fetch');
        });
    }

    /**
     * Breaker state and read counters come from the cache a fixed number of times per request.
     */
    public function testCacheTouchesPerRequestDoNotGrowWithTheListing(): void
    {
        $this->assertConstantCost('cache loads for one listing request', function (int $products): int {
            $store = new InMemoryDocumentStore();
            $cache = new ArrayCache();
            $items = array_map(fn (int $id): Product => $this->product(['entity_id' => $id]), range(1, $products));

            foreach (range(1, $products) as $id) {
                $store->seed('kingletas_catalog_product_1', (string) $id, []);
            }

            $recorder = new FallbackRecorder(new MetricStorage($cache, new Json(), new FakeClock()));
            $this->hydrator($store, $cache, $recorder)->hydrate($this->collection($items), PageType::CategoryListing);
            $recorder->flush();

            return $cache->loads;
        });
    }

    public function testPurgingCostsOneEventPerChunkOfTags(): void
    {
        $this->assertCostPerBatch('purge events', 500, function (int $tags): int {
            $events = 0;
            $manager = $this->createMock(ManagerInterface::class);
            $manager->method('dispatch')->willReturnCallback(static function () use (&$events): void {
                $events++;
            });
            $contexts = $this->createMock(CacheContextFactory::class);
            $contexts->method('create')->willReturnCallback(static fn (): CacheContext => new CacheContext());

            $dispatcher = new TagDispatcher(
                $manager,
                $this->createMock(CacheInterface::class),
                $contexts,
                new NullLogger()
            );
            $dispatcher->dispatch(array_map(static fn (int $id): string => 'cat_p_' . $id, range(1, $tags)));

            return $events;
        }, [1, 1200]);
    }

    private function hydrator(
        InMemoryDocumentStore $store,
        ?ArrayCache $cache = null,
        ?FallbackRecorder $recorder = null
    ): CollectionHydrator {
        $cache ??= new ArrayCache();
        $config = $this->config();
        $contexts = $this->createMock(ReadContextResolver::class);
        $contexts->method('resolve')->willReturn(new ReadContext(PageType::CategoryListing, 1, 1, 0));
        return new CollectionHydrator(
            new DocumentReader(
                $store,
                new IndexNamer($config),
                new CircuitBreaker($cache, $config, new FakeClock()),
                new DocumentSchema()
            ),
            $contexts,
            new ProductHydrator(
                $config,
                new ServedOptions(),
                $this->linkField(),
                new ServedAttributes()
            ),
            $recorder ?? new FallbackRecorder(new MetricStorage($cache, new Json(), new FakeClock()))
        );
    }

    /**
     * @param Product[] $items
     */
    private function collection(array $items): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getStoreId')->willReturn(1);

        return $collection;
    }
}
