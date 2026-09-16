<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

use Kingletas\CatalogIndex\Api\StockReaderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Reads the single-source stock status index for stores without multi-source inventory.
 */
class LegacyStockReader implements StockReaderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(array $productIds, int $websiteId): array
    {
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('cataloginventory_stock_status'),
                    ['product_id', 'qty', 'stock_status', 'stock_id']
                )
                ->where('product_id IN (?)', array_map('intval', $productIds))
                ->where('website_id = ?', 0)
        );
        $levels = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $levels[$productId] = new StockLevel(
                $productId,
                (float) $row['qty'],
                (float) $row['qty'],
                (int) $row['stock_status'] === 1,
                (int) $row['stock_id']
            );
        }

        return $levels;
    }

    /**
     * @inheritDoc
     */
    public function isApplicable(): bool
    {
        return true;
    }
}
