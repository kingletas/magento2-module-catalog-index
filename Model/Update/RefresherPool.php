<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;

/**
 * Finds the refresher registered under a family's name.
 */
class RefresherPool
{
    /**
     * @param array<string, RefresherInterface> $refreshers Keyed by family value.
     */
    public function __construct(
        private readonly array $refreshers = []
    ) {
    }

    public function get(IndexFamily $family): RefresherInterface
    {
        $refresher = $this->refreshers[$family->value] ?? null;

        if (!$refresher instanceof RefresherInterface || $refresher->family() !== $family) {
            throw new InvalidArgumentException((string) __('No refresher is registered for %1.', $family->value));
        }

        return $refresher;
    }
}
