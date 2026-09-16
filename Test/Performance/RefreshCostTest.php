<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Performance;

use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Stock\StockReaderPool;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\DocumentWriter;
use Kingletas\CatalogIndex\Model\Update\StockRefresher;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\InMemoryStockReader;
use Kingletas\CatalogIndex\Test\Support\RecordingPurger;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\Foundation\Test\Support\BudgetAssertions;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * What keeping documents current costs the document store and the database.
 */
class RefreshCostTest extends TestCase
{
    use BudgetAssertions;
    use ShippedConfig;

    private const int BATCH = 50;

    private InMemoryDocumentStore $store;

    private InMemoryStockReader $stock;

    private int $relationQueries = 0;

    /**
     * One stock read, one comparison read and one bulk write per batch, however many products are in it.
     */
    public function testARefreshCostsOneReadAndOneWritePerBatch(): void
    {
        $this->assertCostPerBatch('bulk writes while refreshing stock', self::BATCH, function (int $products): int {
            $this->refresh($products);
            $this->assertSame($this->store->calls('write'), $this->store->calls('fetch'));
            $this->assertSame($this->store->calls('write'), $this->stock->reads);

            return $this->store->calls('write');
        });
    }

    public function testRelationLookupsDoNotGrowWithTheBatch(): void
    {
        $measure = function (int $products): int {
            $this->refresh($products);

            return (int) ceil($this->relationQueries / 3);
        };

        $this->assertCostPerBatch('relation queries while refreshing stock', self::BATCH, $measure);
    }

    private function refresh(int $products): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->stock = new InMemoryStockReader();
        $this->relationQueries = 0;
        $ids = range(1, $products);
        $this->stock->quantities = array_fill_keys($ids, 5.0);
        $config = $this->config(['updates/batch_size' => (string) self::BATCH]);
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('websiteIds')->willReturn([1]);
        $relations = $this->createMock(AffectedProductResolver::class);
        $relations->method('withParents')->willReturnCallback(function (array $ids): array {
            $this->relationQueries++;

            return $ids;
        });
        $relations->method('childrenOf')->willReturnCallback(function (): array {
            $this->relationQueries++;

            return [];
        });
        $relations->method('categoriesOf')->willReturnCallback(function (): array {
            $this->relationQueries++;

            return [];
        });

        (new StockRefresher(
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
            new RecordingPurger(),
            new VersionSource(new FakeClock())
        ))->refresh($ids);
    }
}
