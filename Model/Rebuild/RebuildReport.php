<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Rebuild;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;

/**
 * What a full rebuild did in one scope.
 */
class RebuildReport
{
    public function __construct(
        public readonly IndexFamily $family,
        public readonly int $scopeId,
        public readonly string $index = '',
        public readonly ?string $previousIndex = null,
        public readonly int $documents = 0,
        public readonly int $changed = 0,
        public readonly int $replayed = 0,
        public readonly int $failed = 0,
        public readonly ?string $skippedBecause = null
    ) {
    }

    public function isSkipped(): bool
    {
        return $this->skippedBecause !== null;
    }
}
