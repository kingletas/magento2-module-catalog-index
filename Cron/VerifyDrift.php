<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Cron;

use Kingletas\CatalogIndex\Model\Drift\DriftAlert;
use Kingletas\CatalogIndex\Model\Drift\DriftVerifier;

/**
 * Samples product documents against the database, repairs what drifted and warns past the budget.
 */
class VerifyDrift
{
    public function __construct(
        private readonly DriftVerifier $verifier,
        private readonly DriftAlert $alert
    ) {
    }

    public function execute(): void
    {
        $this->alert->evaluate($this->verifier->verify());
    }
}
