<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Cron;

use Kingletas\CatalogIndex\Model\Schedule\ScheduleRunner;

/**
 * Sends documents whose dated or staged values came due in the last minute.
 */
class RunSchedule
{
    public function __construct(
        private readonly ScheduleRunner $runner
    ) {
    }

    public function execute(): void
    {
        $this->runner->run();
    }
}
