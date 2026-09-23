<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Api\Data\StockLevelInterface;

/**
 * Reads display stock for many products at once.
 *
 * @api
 */
interface StockReaderInterface
{
    /**
     * @param int[] $productIds
     * @return array<int, StockLevelInterface> Keyed by product id; products with no stock record omitted.
     */
    public function read(array $productIds, int $websiteId): array;

    public function isApplicable(): bool;
}
