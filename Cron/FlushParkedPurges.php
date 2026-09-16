<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Cron;

use Kingletas\CatalogIndex\Model\Cache\ParkedPurgeFlusher;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\Foundation\Model\Lock\LockRunner;

/**
 * Purges tags that were parked while another purge held the lock.
 */
class FlushParkedPurges
{
    public function __construct(
        private readonly ParkedPurgeFlusher $flusher,
        private readonly LockRunner $locks,
        private readonly Config $config,
        private readonly string $lockName = 'kingletas_catalog_index_purge',
        private readonly int $lockWaitSeconds = 5
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isPurgeEnabled()) {
            return;
        }

        $this->locks->run($this->lockName, function (): void {
            $this->flusher->flushHeld();
        }, $this->lockWaitSeconds);
    }
}
