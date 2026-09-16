<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Cache;

use Kingletas\CatalogIndex\Model\Cache\TagDispatcher;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\CacheContextFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

class TagDispatcherTest extends TestCase
{
    /** @var array<int, string[]> */
    private array $dispatched = [];

    /** @var array<int, string[]> */
    private array $cleaned = [];

    private bool $eventFails = false;

    public function testTagsAreSentInChunksToBothThePageCacheEventAndTheApplicationCache(): void
    {
        $this->dispatcher()->dispatch(['cat_p_1', 'cat_p_2', 'cat_p_1', 'cat_p_3']);

        $this->assertSame([['cat_p_1', 'cat_p_2'], ['cat_p_3']], $this->dispatched);
        $this->assertSame($this->dispatched, $this->cleaned);
    }

    /**
     * Magento reads an empty tag list as "clean everything", which is the one thing this module never does.
     */
    public function testAnEmptyOrBlankListNeverReachesTheCache(): void
    {
        $this->dispatcher()->dispatch([]);
        $this->dispatcher()->dispatch(['', '  ', 7]);

        $this->assertSame([], $this->dispatched);
        $this->assertSame([], $this->cleaned);
    }

    public function testAFailedChunkIsLoggedAndTheRestStillGoOut(): void
    {
        $this->eventFails = true;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $this->dispatcher($logger)->dispatch(['cat_p_1', 'cat_p_2', 'cat_p_3']);

        $this->assertSame([], $this->cleaned);
    }

    private function dispatcher(?LoggerInterface $logger = null): TagDispatcher
    {
        $events = $this->createMock(ManagerInterface::class);
        $events->method('dispatch')->willReturnCallback(function (string $name, array $data): void {
            $this->assertSame('clean_cache_by_tags', $name);

            if ($this->eventFails) {
                throw new RuntimeException('Varnish refused the purge.');
            }

            $this->dispatched[] = $data['object']->getIdentities();
        });
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('clean')->willReturnCallback(function (array $tags): bool {
            $this->cleaned[] = $tags;

            return true;
        });
        $contexts = $this->createMock(CacheContextFactory::class);
        $contexts->method('create')->willReturnCallback(static fn (): CacheContext => new CacheContext());

        return new TagDispatcher($events, $cache, $contexts, $logger ?? new NullLogger(), 2);
    }
}
