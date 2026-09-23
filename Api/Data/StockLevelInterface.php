<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * What a shopper is shown about one product's stock, never what checkout decides with.
 *
 * @api
 */
interface StockLevelInterface
{
    public function getProductId(): int;

    public function getQuantity(): float;

    public function getSalableQuantity(): float;

    public function isSalable(): bool;

    public function getStockId(): int;
}
