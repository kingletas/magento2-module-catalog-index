<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

use Kingletas\CatalogIndex\Api\Data\StockLevelInterface;

/**
 * What a shopper is shown about one product's stock, never what checkout decides with.
 */
class StockLevel implements StockLevelInterface
{
    public function __construct(
        private readonly int $productId,
        private readonly float $quantity,
        private readonly float $salableQuantity,
        private readonly bool $isSalable,
        private readonly int $stockId
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getProductId(): int
    {
        return $this->productId;
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @inheritDoc
     */
    public function getSalableQuantity(): float
    {
        return $this->salableQuantity;
    }

    /**
     * @inheritDoc
     */
    public function isSalable(): bool
    {
        return $this->isSalable;
    }

    /**
     * @inheritDoc
     */
    public function getStockId(): int
    {
        return $this->stockId;
    }
}
