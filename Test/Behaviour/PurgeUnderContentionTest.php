<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Behaviour;

use Kingletas\CatalogIndex\Cron\FlushParkedPurges;
use Kingletas\CatalogIndex\Model\Cache\ParkedPurgeFlusher;
use Kingletas\CatalogIndex\Model\Cache\ParkedPurges;
use Kingletas\CatalogIndex\Model\Cache\TagDispatcher;
use Kingletas\CatalogIndex\Model\Cache\TagPurger;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\Foundation\Model\Lock\LockRunner;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Two consumers purge at once: one waits its turn under the lock, and nothing it asked for is lost.
 */
class PurgeUnderContentionTest extends TestCase
{
    use ShippedConfig;

    private ?string $holder = null;

    /** @var string[] */
    private array $purged = [];

    /** @var array<int, string> */
    private array $parkedRows = [];

    public function testATagParkedWhileTheLockIsHeldIsPurgedByTheNextHolder(): void
    {
        $this->holder = 'a stock consumer on another server';
        $this->purger()->purge(['cat_p_7', 'cat_c_p_7']);

        $this->assertSame([], $this->purged);
        $this->assertSame(['cat_p_7', 'cat_c_p_7'], array_values($this->parkedRows));

        $this->holder = null;
        $this->purger()->purge(['cat_p_8']);

        $this->assertSame(['cat_p_8', 'cat_p_7', 'cat_c_p_7'], $this->purged);
        $this->assertSame([], $this->parkedRows);
    }

    public function testTheMinuteJobPurgesWhatWasParkedWhenNoOtherPurgeComes(): void
    {
        $this->holder = 'a rebuild purging its changes';
        $this->purger()->purge(['cat_p_7']);
        $this->holder = null;

        (new FlushParkedPurges($this->flusher(), $this->locks(), $this->config()))->execute();

        $this->assertSame(['cat_p_7'], $this->purged);
        $this->assertSame([], $this->parkedRows);
    }

    public function testNothingParkedMeansTheMinuteJobPurgesNothing(): void
    {
        (new FlushParkedPurges($this->flusher(), $this->locks(), $this->config()))->execute();

        $this->assertSame([], $this->purged);
    }

    private function purger(): TagPurger
    {
        return new TagPurger($this->dispatcher(), $this->parked(), $this->flusher(), $this->locks(), $this->config());
    }

    private function flusher(): ParkedPurgeFlusher
    {
        return new ParkedPurgeFlusher($this->parked(), $this->dispatcher());
    }

    private function locks(): LockRunner
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(fn (): bool => $this->holder === null);

        return new LockRunner($locks, new NullLogger());
    }

    private function dispatcher(): TagDispatcher
    {
        $events = $this->createMock(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(function (string $name, array $data): void {
            array_push($this->purged, ...$data['object']->getIdentities());
        });
        $contexts = $this->createMock(CacheContextFactory::class);
        $contexts->method('create')->willReturnCallback(static fn (): CacheContext => new CacheContext());

        return new TagDispatcher($events, $this->createMock(CacheInterface::class), $contexts, new NullLogger());
    }

    private function parked(): ParkedPurges
    {
        $parked = $this->createMock(ParkedPurges::class);
        $parked->method('park')->willReturnCallback(function (array $tags): void {
            foreach ($tags as $tag) {
                $this->parkedRows[count($this->parkedRows) + 1] = $tag;
            }
        });
        $parked->method('oldest')->willReturnCallback(
            fn (): array => $this->parkedRows === []
                ? [0, []]
                : [max(array_keys($this->parkedRows)), array_values(array_unique($this->parkedRows))]
        );
        $parked->method('release')->willReturnCallback(function (int $upToId): void {
            $this->parkedRows = array_filter(
                $this->parkedRows,
                static fn (int $id): bool => $id > $upToId,
                ARRAY_FILTER_USE_KEY
            );
        });

        return $parked;
    }
}
