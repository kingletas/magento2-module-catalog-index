<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContextFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends tags through the same event Magento's own indexers use, so the page cache and Varnish both hear it.
 */
class TagDispatcher
{
    public function __construct(
        private readonly EventManager $eventManager,
        private readonly CacheInterface $cache,
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly LoggerInterface $logger,
        private readonly int $tagsPerPurge = 500
    ) {
    }

    /**
     * An empty tag list cleans everything in Magento, so only non-blank, de-duplicated tags are sent.
     *
     * @param mixed[] $tags
     */
    public function dispatch(array $tags): void
    {
        foreach (array_chunk($this->clean($tags), max(1, $this->tagsPerPurge)) as $chunk) {
            try {
                $context = $this->cacheContextFactory->create();
                $context->registerTags($chunk);
                $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $context]);
                $this->cache->clean($chunk);
            } catch (Throwable $e) {
                $this->logger->error(
                    sprintf('Catalog index: purging %d cache tags failed.', count($chunk)),
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * @param mixed[] $tags
     * @return string[]
     */
    public function clean(array $tags): array
    {
        return array_values(array_unique(array_filter(
            $tags,
            static fn (mixed $tag): bool => is_string($tag) && trim($tag) !== ''
        )));
    }
}
