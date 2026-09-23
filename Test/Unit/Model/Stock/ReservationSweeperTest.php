<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Stock;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Stock\ReservationSweeper;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class ReservationSweeperTest extends TestCase
{
    use ShippedConfig;
    use StubbedDatabase;

    /** @var array<string, mixed> */
    private array $state = [];

    /** @var int[][] */
    private array $published = [];

    /**
     * Starting from zero would replay every reservation the store ever made on the first run.
     */
    public function testTheFirstSweepStartsFromTheNewestReservation(): void
    {
        $this->answers['inventory_reservation'] = '8123';

        $this->assertSame(0, $this->sweeper()->sweep());
        $this->assertSame(['reservation_id' => 8123], $this->state['watermark:reservation']);
        $this->assertSame([], $this->published);
    }

    public function testNewReservationsRefreshTheirProductsAndMoveTheWatermark(): void
    {
        $this->state['watermark:reservation'] = ['reservation_id' => 10];
        $this->answers['inventory_reservation'] = ['11' => 'SKU-5', '12' => 'SKU-5', '14' => 'SKU-6'];
        $this->answers['catalog_product_entity'] = ['5', '6'];

        $this->assertSame(2, $this->sweeper()->sweep());
        $this->assertSame([[5, 6]], $this->published);
        $this->assertSame(['reservation_id' => 14], $this->state['watermark:reservation']);
    }

    public function testAStoreWithoutMultiSourceInventorySweepsNothing(): void
    {
        $this->tablesExist = false;

        $this->assertSame(0, $this->sweeper()->sweep());
    }

    private function sweeper(): ReservationSweeper
    {
        $state = $this->createMock(StateStorage::class);
        $state->method('get')->willReturnCallback(fn (string $key): ?array => $this->state[$key] ?? null);
        $state->method('set')->willReturnCallback(function (string $key, array $value): void {
            $this->state[$key] = $value;
        });
        $publisher = $this->createMock(RefreshPublisher::class);
        $publisher->method('publish')->willReturnCallback(function (IndexFamily $family, array $ids): void {
            $this->assertSame(IndexFamily::Stock, $family);
            $this->published[] = $ids;
        });

        return new ReservationSweeper($this->config(), $this->resourceConnection(), $state, $publisher);
    }
}
