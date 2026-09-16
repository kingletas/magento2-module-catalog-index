<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Schedule;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Staging\ScheduledVersionScanner;
use Kingletas\CatalogIndex\Model\Staging\StagingMode;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;

/**
 * Sends documents whose dated values or staged versions have come due to be rebuilt.
 */
class ScheduleRunner
{
    private const string STAGING_WATERMARK = 'watermark:staging';

    public function __construct(
        private readonly Config $config,
        private readonly ScheduleStorage $storage,
        private readonly StagingMode $stagingMode,
        private readonly ScheduledVersionScanner $scanner,
        private readonly StateStorage $state,
        private readonly RefreshPublisher $publisher,
        private readonly ClockInterface $clock,
        private readonly int $limit = 5000
    ) {
    }

    /**
     * @return int How many ids were sent.
     */
    public function run(): int
    {
        if (!$this->config->isEnabled()) {
            return 0;
        }

        $now = $this->clock->now()->getTimestamp();
        $sent = $this->config->isScheduleEnabled() ? $this->runDue($now) : 0;

        return $sent + ($this->stagingMode->isActive() ? $this->runStaging($now) : 0);
    }

    private function runDue(int $now): int
    {
        $due = $this->storage->due($now, $this->limit);
        $byFamily = [];

        foreach ($due as $refresh) {
            $byFamily[$refresh->family->value][] = $refresh->entityId;
        }

        foreach ($byFamily as $family => $ids) {
            $this->publisher->publish(IndexFamily::from($family), $ids, RefreshRequest::REASON_SCHEDULE);
        }

        $this->storage->remove(array_map(static fn (ScheduledRefresh $refresh): int => $refresh->scheduleId, $due));

        return count($due);
    }

    private function runStaging(int $now): int
    {
        $from = (int) ($this->state->get(self::STAGING_WATERMARK)['at'] ?? ($now - 60));
        $products = $this->scanner->products($from, $now);
        $categories = $this->scanner->categories($from, $now);

        if ($products !== []) {
            $this->publisher->publish(IndexFamily::Product, $products, RefreshRequest::REASON_SCHEDULE);
            $this->publisher->publish(IndexFamily::Price, $products, RefreshRequest::REASON_SCHEDULE);
        }

        if ($categories !== []) {
            $this->publisher->publish(IndexFamily::Category, $categories, RefreshRequest::REASON_SCHEDULE);
        }

        $this->state->set(self::STAGING_WATERMARK, ['at' => $now]);

        return count($products) + count($categories);
    }
}
