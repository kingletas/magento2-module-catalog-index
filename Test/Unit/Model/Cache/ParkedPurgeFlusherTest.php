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
use PHPUnit\Framework\TestCase;

class ParkedPurgeFlusherTest extends TestCase
{
    public function testParkedTagsArePurgedThenReleasedRoundByRound(): void
    {
        $rounds = [[3, ['cat_p_1', 'cat_p_2']], [5, ['cat_p_3']], [0, []]];
        $parked = $this->createMock(ParkedPurges::class);
        $parked->method('oldest')->with(2)->willReturnCallback(static function () use (&$rounds): array {
            return array_shift($rounds);
        });
        $events = [];
        $parked->method('release')->willReturnCallback(static function (int $upToId) use (&$events): void {
            $events[] = 'release ' . $upToId;
        });
        $dispatcher = $this->createMock(TagDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (array $tags) use (&$events): void {
            $events[] = 'purge ' . implode(' ', $tags);
        });

        $purged = (new ParkedPurgeFlusher($parked, $dispatcher, 2))->flushHeld();

        $this->assertSame(3, $purged);
        $this->assertSame(['purge cat_p_1 cat_p_2', 'release 3', 'purge cat_p_3', 'release 5'], $events);
    }

    /**
     * A steady stream of parked tags cannot keep one holder in the lock forever.
     */
    public function testRoundsAreBounded(): void
    {
        $parked = $this->createMock(ParkedPurges::class);
        $parked->expects($this->exactly(3))->method('oldest')->willReturn([1, ['cat_p_1']]);

        $purged = (new ParkedPurgeFlusher($parked, $this->createMock(TagDispatcher::class), 10, 3))->flushHeld();

        $this->assertSame(3, $purged);
    }
}
