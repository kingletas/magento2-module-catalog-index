<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Api\StockReaderInterface;
use Kingletas\CatalogIndex\Model\Stock\StockLevel;

/**
 * Stock levels a test changes the way an order would.
 */
class InMemoryStockReader implements StockReaderInterface
{
    /** @var array<int, float> */
    public array $quantities = [];

    public int $reads = 0;

    public function read(array $productIds, int $websiteId): array
    {
        $this->reads++;
        $levels = [];

        foreach ($productIds as $id) {
            if (isset($this->quantities[$id])) {
                $quantity = $this->quantities[$id];
                $levels[$id] = new StockLevel($id, $quantity, $quantity, $quantity > 0, 1);
            }
        }

        return $levels;
    }

    public function isApplicable(): bool
    {
        return true;
    }
}
