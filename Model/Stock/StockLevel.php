<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

/**
 * What a shopper is shown about one product's stock, never what checkout decides with.
 */
class StockLevel
{
    public function __construct(
        public readonly int $productId,
        public readonly float $quantity,
        public readonly float $salableQuantity,
        public readonly bool $isSalable,
        public readonly int $stockId
    ) {
    }
}
