<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Drift;

/**
 * How far one store view's product documents had drifted from the database, from a sample.
 */
class DriftReport
{
    /**
     * @param int[] $drifted Products whose stored document differed from a fresh build.
     */
    public function __construct(
        public readonly int $storeId,
        public readonly int $checked,
        public readonly array $drifted = [],
        public readonly bool $repaired = false
    ) {
    }

    public function ratio(): float
    {
        return $this->checked === 0 ? 0.0 : count($this->drifted) / $this->checked;
    }
}
