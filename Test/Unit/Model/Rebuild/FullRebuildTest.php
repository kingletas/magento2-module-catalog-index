<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Rebuild;

use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Model\Index\IndexDefinition;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Rebuild\ChangelogReader;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Rebuild\IdSource;
use Kingletas\CatalogIndex\Model\Rebuild\RebuildPurger;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\Foundation\Model\Lock\LockRunner;
use Kingletas\Foundation\Test\Support\FakeClock;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FullRebuildTest extends TestCase
{
    use ShippedConfig;

    private InMemoryDocumentStore $store;

    /** @var array<int, array{0: int[], 1: string, 2: string}> */
    private array $refreshes = [];

    /** @var int[] */
    private array $versions = [];

    private bool $locked = false;

    private bool $failBuild = false;

    protected function setUp(): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->store->createIndex('kingletas_catalog_product_1_20260101000000000000', []);
        $this->store->createIndex('kingletas_catalog_product_1_20260201000000000000', []);
        $this->store->pointAlias('kingletas_catalog_product_1', 'kingletas_catalog_product_1_20260201000000000000');
        $this->versions = [10, 12, 13];
    }

    /**
     * Anything changed while the build ran is replayed into the new index before the alias moves to it.
     */
    public function testTheBuildReplaysTheChangeLogThenSwitchesTheAliasAndPrunesOldBuilds(): void
    {
        $report = $this->rebuild()->run(IndexFamily::Product)[0];

        $new = 'kingletas_catalog_product_1_20260915120000000000';
        $this->assertSame($new, $this->store->aliases['kingletas_catalog_product_1']);
        $this->assertSame(
            [
                [[1, 2], $new, 'kingletas_catalog_product_1'],
                [[3], $new, 'kingletas_catalog_product_1'],
                [[99], $new, 'kingletas_catalog_product_1'],
                [[100], 'kingletas_catalog_product_1', 'kingletas_catalog_product_1'],
            ],
            $this->refreshes
        );
        $this->assertSame(2, $report->replayed);
        $this->assertSame(1, $this->store->calls('refresh'));
        $this->assertSame('kingletas_catalog_product_1_20260201000000000000', $report->previousIndex);
        $this->assertArrayNotHasKey('kingletas_catalog_product_1_20260101000000000000', $this->store->indexes);
        $this->assertArrayHasKey('kingletas_catalog_product_1_20260201000000000000', $this->store->indexes);
    }

    public function testAFailedBuildDropsTheHalfBuiltIndexAndLeavesTheAliasAlone(): void
    {
        $this->failBuild = true;

        try {
            $this->rebuild()->run(IndexFamily::Product);
            $this->fail('The build failure should have surfaced.');
        } catch (\RuntimeException) {
            $this->assertArrayNotHasKey('kingletas_catalog_product_1_20260915120000000000', $this->store->indexes);
            $this->assertSame(
                'kingletas_catalog_product_1_20260201000000000000',
                $this->store->aliases['kingletas_catalog_product_1']
            );
        }
    }

    public function testASecondRebuildOfTheSameIndexIsSkipped(): void
    {
        $this->locked = true;

        $report = $this->rebuild()->run(IndexFamily::Product)[0];

        $this->assertTrue($report->isSkipped());
        $this->assertSame([], $this->refreshes);
    }

    public function testADisabledModuleSkipsWithAReason(): void
    {
        $report = $this->rebuild(['general/enabled' => '0'])->run(IndexFamily::Product)[0];

        $this->assertSame('the catalog index is disabled', $report->skippedBecause);
    }

    /**
     * @param array<string, string> $config
     */
    private function rebuild(array $config = []): FullRebuild
    {
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('scopeIds')->willReturn([1]);
        $refresher = $this->createMock(RefresherInterface::class);
        $refresher->method('family')->willReturn(IndexFamily::Product);
        $refresher->method('refreshInto')->willReturnCallback(
            function (array $ids, int $scope, string $write, string $compare): ChangeSetInterface {
                if ($this->failBuild) {
                    throw new \RuntimeException('store refused');
                }

                $this->refreshes[] = [$ids, $write, $compare];
                $this->store->write(
                    $write,
                    array_map(static fn (int $id): Document => new Document((string) $id, 1, []), $ids)
                );
                $set = new ChangeSet();
                $set->add(new Change($ids[0], true));
                $set->record(count($ids), 0, []);

                return $set;
            }
        );
        $ids = $this->createMock(IdSource::class);
        $ids->method('batches')->willReturnCallback(static function (): \Generator {
            yield [1, 2];
            yield [3];
        });
        $changelog = $this->createMock(ChangelogReader::class);
        $changelog->method('currentVersion')->willReturnCallback(fn (): ?int => array_shift($this->versions));
        $changelog->method('idsBetween')->willReturnCallback(
            static fn (IndexFamily $family, int $from, int $to): array => $from === 10 ? [99] : [100]
        );
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(fn (): bool => !$this->locked);

        return new FullRebuild(
            $this->config($config),
            $scopes,
            new IndexNamer($this->config()),
            new IndexDefinition($this->config()),
            $this->store,
            new RefresherPool(['product' => $refresher]),
            $ids,
            $changelog,
            $this->createMock(StateStorage::class),
            $this->createMock(RebuildPurger::class),
            new LockRunner($locks, new NullLogger()),
            new FakeClock()
        );
    }
}
