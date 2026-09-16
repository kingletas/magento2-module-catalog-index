<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

use Kingletas\CatalogIndex\Api\CachePurgerInterface;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\Foundation\Model\Lock\LockRunner;

/**
 * Purges one caller at a time under a lock every server shares, parking tags that cannot wait for it.
 */
class TagPurger implements CachePurgerInterface
{
    public function __construct(
        private readonly TagDispatcher $dispatcher,
        private readonly ParkedPurges $parked,
        private readonly ParkedPurgeFlusher $flusher,
        private readonly LockRunner $locks,
        private readonly Config $config,
        private readonly string $lockName = 'kingletas_catalog_index_purge',
        private readonly int $lockWaitSeconds = 5
    ) {
    }

    /**
     * A parked tag is purged by the next caller that gets the lock, or by the per-minute job, so none is lost.
     *
     * @inheritDoc
     */
    public function purge(array $tags): void
    {
        $tags = $this->dispatcher->clean($tags);

        if ($tags === [] || !$this->config->isPurgeEnabled()) {
            return;
        }

        $ran = $this->locks->run($this->lockName, function () use ($tags): void {
            $this->dispatcher->dispatch($tags);
            $this->flusher->flushHeld();
        }, $this->lockWaitSeconds);

        if (!$ran) {
            $this->parked->park($tags);
        }
    }
}
