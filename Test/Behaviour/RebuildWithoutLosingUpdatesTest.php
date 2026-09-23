<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Behaviour;

use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\PriceDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexDefinition;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Rebuild\ChangelogReader;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Rebuild\IdSource;
use Kingletas\CatalogIndex\Model\Rebuild\RebuildPurger;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\DocumentWriter;
use Kingletas\CatalogIndex\Model\Update\PriceRefresher;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\RecordingPurger;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\ViewInterface;
use Magento\Framework\Mview\ViewInterfaceFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Kingletas\Foundation\Model\Lock\LockRunner;

/**
 * A price changes while a full rebuild runs, and the new index still goes live with it.
 */
class RebuildWithoutLosingUpdatesTest extends TestCase
{
    use ShippedConfig;
    use StubbedDatabase;

    /** @var array<int, float> Product id to its indexed final price. */
    private array $prices = [1 => 10.0, 2 => 20.0, 3 => 30.0];

    /** @var array<int, int> Change log version to product id. */
    private array $changelog = [];

    private int $priceReads = 0;

    private InMemoryDocumentStore $store;

    private RecordingPurger $purger;

    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->purger = new RecordingPurger();
        $this->clock = new FakeClock();
        $this->answers['catalog_product_index_price'] = fn (array $query): array => $this->priceRows($query);
    }

    public function testAChangeMadeMidBuildIsReplayedBeforeTheAliasMoves(): void
    {
        $this->rebuild()->run(IndexFamily::Price);
        $this->purger->purges = [];
        $this->priceReads = 0;
        $this->clock->advance('+1 hour');

        $report = $this->rebuild(changeDuringBuild: [2 => 25.0])->run(IndexFamily::Price)[0];

        $live = $this->store->aliases['kingletas_catalog_price_1'];
        $this->assertSame($report->index, $live);
        $this->assertSame(25.0, $this->store->indexes[$live]['2']->get('groups')['0']['final_price']);
        $this->assertSame(1, $report->replayed);
        $this->assertSame(['cat_p_2'], $this->purger->tags());
    }

    /**
     * A second rebuild with nothing changed has nothing to purge, so a nightly rebuild leaves the cache warm.
     */
    public function testARebuildThatFindsNothingNewPurgesNothingAndKeepsOnePreviousBuild(): void
    {
        $this->rebuild()->run(IndexFamily::Price);
        $this->clock->advance('+1 hour');
        $this->rebuild()->run(IndexFamily::Price);
        $this->purger->purges = [];
        $this->clock->advance('+1 hour');

        $this->rebuild()->run(IndexFamily::Price);

        $this->assertSame([], $this->purger->tags());
        $this->assertCount(2, $this->store->listIndexes('kingletas_catalog_price_1_'));
    }

    /**
     * @param array<int, float> $changeDuringBuild
     */
    private function rebuild(array $changeDuringBuild = []): FullRebuild
    {
        $config = $this->config(['updates/batch_size' => '2']);
        $resource = $this->resourceConnection();
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('scopeIds')->willReturn([1]);
        $scopes->method('websiteIds')->willReturn([1]);
        $this->answers['catalog_product_index_price'] = function (array $query) use ($changeDuringBuild): array {
            if (++$this->priceReads === 2 && $changeDuringBuild !== []) {
                foreach ($changeDuringBuild as $id => $price) {
                    $this->prices[$id] = $price;
                    $this->changelog[count($this->changelog) + 1] = $id;
                }
            }

            return $this->priceRows($query);
        };
        $refresher = new PriceRefresher(
            $config,
            $scopes,
            new IndexNamer($config),
            new PriceDocumentBuilder($resource, new FingerprintCalculator()),
            new DocumentWriter($this->store, new NullLogger()),
            new PurgePlanner(),
            $this->purger,
            new VersionSource($this->clock)
        );
        $ids = $this->createMock(IdSource::class);
        $ids->method('batches')->willReturnCallback(static function (): \Generator {
            yield [1, 2];
            yield [3];
        });
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);

        return new FullRebuild(
            $config,
            $scopes,
            new IndexNamer($config),
            new IndexDefinition($config),
            $this->store,
            new RefresherPool(['price' => $refresher]),
            $ids,
            new ChangelogReader($this->views(), ['price' => 'kingletas_catalog_index_price']),
            new StateStorage($resource, new Json()),
            new RebuildPurger(new PurgePlanner(), $this->purger, $this->createMock(AffectedProductResolver::class)),
            new LockRunner($locks, new NullLogger()),
            $this->clock
        );
    }

    /**
     * @param array{table: string, calls: array<int, array{0: string, 1: mixed[]}>} $query
     * @return array<int, array<string, mixed>>
     */
    private function priceRows(array $query): array
    {
        $ids = [];

        foreach ($query['calls'] as [$method, $args]) {
            if ($method === 'where' && $args[0] === 'entity_id IN (?)') {
                $ids = $args[1];
            }
        }

        $rows = [];

        foreach ($ids as $id) {
            $price = $this->prices[$id];
            $rows[] = [
                'entity_id' => $id, 'customer_group_id' => 0, 'price' => $price, 'final_price' => $price,
                'min_price' => $price, 'max_price' => $price, 'tier_price' => null,
            ];
        }

        return $rows;
    }

    private function views(): ViewInterfaceFactory
    {
        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getVersion')->willReturnCallback(fn (): int => count($this->changelog));
        $changelog->method('getList')->willReturnCallback(
            fn (int $from, int $to): array => array_values(array_intersect_key(
                $this->changelog,
                array_flip(range($from + 1, max($from + 1, $to)))
            ))
        );
        $view = $this->createMock(ViewInterface::class);
        $view->method('load')->willReturnSelf();
        $view->method('isEnabled')->willReturn(true);
        $view->method('getChangelog')->willReturn($changelog);
        $factory = $this->createMock(ViewInterfaceFactory::class);
        $factory->method('create')->willReturn($view);

        return $factory;
    }
}
