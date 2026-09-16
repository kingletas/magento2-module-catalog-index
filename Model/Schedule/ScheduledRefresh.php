<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Schedule;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;

/**
 * One document due to be rebuilt at a known moment.
 */
class ScheduledRefresh
{
    public function __construct(
        public readonly int $scheduleId,
        public readonly IndexFamily $family,
        public readonly int $entityId,
        public readonly int $scopeId,
        public readonly int $dueAt
    ) {
    }
}
