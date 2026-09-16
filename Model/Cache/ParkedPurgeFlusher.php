<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

/**
 * Purges parked tags in bounded rounds; the caller must already hold the purge lock.
 */
class ParkedPurgeFlusher
{
    public function __construct(
        private readonly ParkedPurges $parked,
        private readonly TagDispatcher $dispatcher,
        private readonly int $tagsPerRound = 500,
        private readonly int $maxRounds = 20
    ) {
    }

    /**
     * @return int Tags purged.
     */
    public function flushHeld(): int
    {
        $purged = 0;

        for ($round = 0; $round < $this->maxRounds; $round++) {
            [$upToId, $tags] = $this->parked->oldest($this->tagsPerRound);

            if ($tags === []) {
                break;
            }

            $this->dispatcher->dispatch($tags);
            $this->parked->release($upToId);
            $purged += count($tags);
        }

        return $purged;
    }
}
