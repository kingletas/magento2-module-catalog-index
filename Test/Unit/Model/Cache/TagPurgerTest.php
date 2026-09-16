<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Cache;

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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TagPurgerTest extends TestCase
{
    use ShippedConfig;

    /** @var string[] What happened, in order. */
    private array $events = [];

    private bool $lockFree = true;

    private ParkedPurges&MockObject $parked;

    private ParkedPurgeFlusher&MockObject $flusher;

    protected function setUp(): void
    {
        $this->parked = $this->createMock(ParkedPurges::class);
        $this->flusher = $this->createMock(ParkedPurgeFlusher::class);
        $this->flusher->method('flushHeld')->willReturnCallback(function (): int {
            $this->events[] = 'flush parked';

            return 0;
        });
    }

    public function testTagsArePurgedWhileHoldingTheSharedLockAndParkedTagsFollow(): void
    {
        $this->parked->expects($this->never())->method('park');

        $this->purger()->purge(['cat_p_1', 'cat_p_2', 'cat_p_1']);

        $this->assertSame(
            ['lock purge for 5s', 'purge cat_p_1 cat_p_2', 'flush parked', 'unlock purge'],
            $this->events
        );
    }

    public function testTagsAreParkedRatherThanPurgedWithoutTheLock(): void
    {
        $this->lockFree = false;
        $this->parked->expects($this->once())->method('park')->with(['cat_p_1', 'cat_p_2']);

        $this->purger()->purge(['cat_p_1', 'cat_p_2']);

        $this->assertSame(['lock purge for 5s'], $this->events);
    }

    /**
     * Magento reads an empty tag list as "clean everything", which is the one thing this module never does.
     */
    public function testAnEmptyOrBlankListNeitherPurgesNorParks(): void
    {
        $this->parked->expects($this->never())->method('park');

        $this->purger()->purge([]);
        $this->purger()->purge(['', '  ']);

        $this->assertSame([], $this->events);
    }

    public function testTurningPurgingOffNeitherPurgesNorParks(): void
    {
        $this->parked->expects($this->never())->method('park');

        $this->purger(['purge/enabled' => '0'])->purge(['cat_p_1']);

        $this->assertSame([], $this->events);
    }

    /**
     * @param array<string, string> $config
     */
    private function purger(array $config = []): TagPurger
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(function (string $name, int $timeout): bool {
            $this->events[] = sprintf('lock %s for %ds', $name, $timeout);

            return $this->lockFree;
        });
        $locks->method('unlock')->willReturnCallback(function (string $name): bool {
            $this->events[] = 'unlock ' . $name;

            return true;
        });
        $manager = $this->createMock(ManagerInterface::class);
        $manager->method('dispatch')->willReturnCallback(function (string $name, array $data): void {
            $this->events[] = 'purge ' . implode(' ', $data['object']->getIdentities());
        });
        $contexts = $this->createMock(CacheContextFactory::class);
        $contexts->method('create')->willReturnCallback(static fn (): CacheContext => new CacheContext());
        $cache = $this->createMock(CacheInterface::class);
        $dispatcher = new TagDispatcher($manager, $cache, $contexts, new NullLogger());

        return new TagPurger(
            $dispatcher,
            $this->parked,
            $this->flusher,
            new LockRunner($locks, new NullLogger()),
            $this->config($config),
            'purge',
            5
        );
    }
}
