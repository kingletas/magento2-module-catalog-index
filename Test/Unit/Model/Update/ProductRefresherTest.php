<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\ProductRefresher;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class ProductRefresherTest extends RefresherTestCase
{
    /** @var int[][] */
    private array $built = [];

    /** @var array<int, array{0: IndexFamily, 1: int, 2: array<int, mixed>}> */
    private array $scheduled = [];

    public function testEveryStoreViewIsRefreshedWithParentsAndOnlyChangesArePurged(): void
    {
        $this->refresher()->refresh([51]);

        $this->assertSame([[5, 51], [5, 51]], $this->built);
        $this->assertArrayHasKey('51', $this->store->indexes['kingletas_catalog_product_1']);
        $this->assertArrayHasKey('5', $this->store->indexes['kingletas_catalog_product_2']);
        $this->assertSame(['cat_c_p_12', 'cat_p_5', 'cat_p_51'], $this->purger->tags());
        $this->assertSame(IndexFamily::Product, $this->scheduled[0][0]);
    }

    public function testRebuildingTheSameDataPurgesNothingTheSecondTime(): void
    {
        $this->refresher()->refresh([51]);
        $this->purger->purges = [];
        $this->clock->advance('+1 second');

        $this->refresher()->refresh([51]);

        $this->assertSame([], $this->purger->tags());
    }

    public function testADisabledModuleDoesNothing(): void
    {
        $this->refresher(['general/enabled' => '0'])->refresh([51]);

        $this->assertSame([], $this->built);
    }

    /**
     * @param array<string, string> $config
     */
    private function refresher(array $config = []): ProductRefresher
    {
        $builder = $this->createMock(ProductDocumentBuilder::class);
        $build = function (array $ids, BuildContextInterface $context): BuildBatch {
            $this->built[] = $ids;
            $documents = array_map(
                static fn (int $id): Document => new Document((string) $id, $context->getVersion(), [
                    '_fp' => ['listing' => 'same-' . $id],
                    'category_ids' => [12],
                ]),
                $ids
            );

            $moments = [51 => [new DateTimeImmutable('@2000000000')]];

            return new BuildBatch($context->getVersion(), $documents, [], $moments);
        };
        $builder->method('build')->willReturnCallback($build);
        $schedule = $this->createMock(ScheduleStorage::class);
        $schedule->method('record')->willReturnCallback(function (
            IndexFamily $family,
            int $scope,
            array $moments
        ): void {
            $this->scheduled[] = [$family, $scope, $moments];
        });
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturn('UTC');

        return new ProductRefresher(
            $this->config($config),
            $this->scopes(),
            $this->namer(),
            $builder,
            $this->writer(),
            $this->relations([51 => [5]]),
            new PurgePlanner(),
            $this->purger,
            $schedule,
            $this->versions(),
            $this->clock,
            $timezone
        );
    }
}
