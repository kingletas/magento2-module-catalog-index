<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Cron;

use Kingletas\CatalogIndex\Model\Stock\ReservationSweeper;

/**
 * Refreshes stock for products reserved since the last sweep.
 */
class SweepReservations
{
    public function __construct(
        private readonly ReservationSweeper $sweeper
    ) {
    }

    public function execute(): void
    {
        $this->sweeper->sweep();
    }
}
