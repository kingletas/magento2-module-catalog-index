<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Drift;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Psr\Log\LoggerInterface;

/**
 * Warns when drift passes its budget, and repeats only when it gets worse or a quiet period has passed.
 */
class DriftAlert
{
    private const string STATE = 'alert:drift';

    public function __construct(
        private readonly Config $config,
        private readonly StateStorage $state,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
        private readonly int $quietSeconds = 21600
    ) {
    }

    /**
     * @param DriftReport[] $reports
     * @return bool Whether a warning was written.
     */
    public function evaluate(array $reports): bool
    {
        $worst = 0.0;

        foreach ($reports as $report) {
            $worst = max($worst, $report->ratio());
        }

        $now = $this->clock->now()->getTimestamp();
        $last = $this->state->get(self::STATE) ?? [];

        if ($worst <= $this->config->getDriftAlertRatio()) {
            if ($last !== []) {
                $this->state->set(self::STATE, []);
            }

            return false;
        }

        $quiet = $now - (int) ($last['at'] ?? 0) < $this->quietSeconds;

        if ($quiet && $worst < 2 * (float) ($last['ratio'] ?? 0.0)) {
            return false;
        }

        $this->logger->warning(sprintf(
            'Catalog index: %.1f%% of sampled product documents differed from the database and were rebuilt.',
            $worst * 100
        ));
        $this->state->set(self::STATE, ['at' => $now, 'ratio' => $worst]);

        return true;
    }
}
