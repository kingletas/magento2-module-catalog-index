<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Behaviour;

use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Stock\StockReaderPool;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\DocumentWriter;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\StockRefresher;
use Kingletas\CatalogIndex\Observer\RefreshOrderedProducts;
use Kingletas\CatalogIndex\Queue\RefreshConsumer;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\InMemoryStockReader;
use Kingletas\CatalogIndex\Test\Support\RecordingPurger;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * An order is placed, and the storefront stops offering what sold out without flushing anything else.
 */
class OrderToStorefrontTest extends TestCase
{
    use ShippedConfig;

    /** @var string[] */
    private array $queue = [];

    private InMemoryDocumentStore $store;

    private InMemoryStockReader $stock;

    private RecordingPurger $purger;

    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->stock = new InMemoryStockReader();
        $this->stock->quantities = [10 => 0.0, 11 => 2.0, 12 => 40.0];
        $this->purger = new RecordingPurger();
        $this->clock = new FakeClock();
        $this->consumer()->process('{"family":"stock","ids":[10,11,12],"reason":"manual"}');
        $this->purger->purges = [];
    }

    public function testSellingTheLastUnitsTakesTheProductAndItsParentOffCategoryPages(): void
    {
        $this->placeOrder([11 => 2]);

        $this->assertSame(['kingletas.catalog_index.priority'], array_keys($this->queue));
        $this->drainQueue();

        $this->assertFalse($this->store->indexes['kingletas_catalog_stock_1']['11']->get('is_salable'));
        $this->assertSame(
            ['11' => false],
            array_intersect_key($this->store->indexes['kingletas_catalog_stock_1']['10']->get('children'), ['11' => 1])
        );
        $this->assertSame(['cat_c_p_30', 'cat_p_10', 'cat_p_11'], $this->purger->tags());
    }

    /**
     * Forty down to thirty-nine changes nothing a shopper sees, so not one page leaves the cache.
     */
    public function testAnOrderThatLeavesStockBehindPurgesNothing(): void
    {
        $this->placeOrder([12 => 1]);
        $this->drainQueue();

        $this->assertSame(39.0, $this->store->indexes['kingletas_catalog_stock_1']['12']->get('qty'));
        $this->assertSame([], $this->purger->tags());
    }

    /**
     * The consumer runs on its own, and an outage there must never make checkout fail.
     */
    public function testAnUnreachableStoreNeitherBreaksCheckoutNorLosesTheQueue(): void
    {
        $this->store->unreachable = true;

        $this->placeOrder([11 => 2]);
        $this->drainQueue();
        $this->store->unreachable = false;
        $this->consumer()->process('{"family":"stock","ids":[11],"reason":"reservation"}');

        $this->assertFalse($this->store->indexes['kingletas_catalog_stock_1']['11']->get('is_salable'));
    }

    /**
     * @param array<int, int> $lines Product id to quantity bought.
     */
    private function placeOrder(array $lines): void
    {
        $items = [];

        foreach ($lines as $productId => $quantity) {
            $this->stock->quantities[$productId] -= $quantity;
            $items[] = new DataObject(['product_id' => $productId]);
        }

        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn($items);
        $queue = $this->createMock(PublisherInterface::class);
        $queue->method('publish')->willReturnCallback(function (string $topic, string $message): void {
            $this->queue[$topic][] = $message;
        });
        $publisher = new RefreshPublisher($this->config(), $queue, new RefresherPool([]), new Json(), new NullLogger());

        (new RefreshOrderedProducts($publisher))->execute(new Observer(['event' => new Event(['order' => $order])]));
        $this->clock->advance('+1 second');
    }

    private function drainQueue(): void
    {
        foreach ($this->queue as $messages) {
            foreach ($messages as $message) {
                $this->consumer()->process($message);
            }
        }

        $this->queue = [];
    }

    private function consumer(): RefreshConsumer
    {
        $config = $this->config();
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('websiteIds')->willReturn([1]);
        $relations = $this->createMock(AffectedProductResolver::class);
        $relations->method('withParents')->willReturnCallback(
            static fn (array $ids): array => array_values(
                array_unique(array_merge($ids, array_intersect($ids, [11]) === [] ? [] : [10]))
            )
        );
        $relations->method('childrenOf')->willReturnCallback(
            static fn (array $ids): array => in_array(10, $ids, true) ? [10 => [11]] : []
        );
        $relations->method('categoriesOf')->willReturnCallback(
            static fn (array $ids): array => array_intersect_key([10 => [30], 11 => [30], 12 => [31]], array_flip($ids))
        );
        $refresher = new StockRefresher(
            $config,
            $scopes,
            new IndexNamer($config),
            new StockDocumentBuilder(
                new StockReaderPool(['memory' => $this->stock]),
                $relations,
                new FingerprintCalculator(),
                $config
            ),
            new DocumentWriter($this->store, new NullLogger()),
            $relations,
            new PurgePlanner(),
            $this->purger,
            new VersionSource($this->clock)
        );

        return new RefreshConsumer(
            new RefresherPool(['stock' => $refresher]),
            $this->createMock(FullRebuild::class),
            new Json(),
            new NullLogger()
        );
    }
}
