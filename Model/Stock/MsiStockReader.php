<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

use Kingletas\CatalogIndex\Api\StockReaderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\Manager as ModuleManager;
use Zend_Db_Expr;

/**
 * Reads multi-source stock and subtracts open reservations, straight from the tables so no inventory class is required.
 */
class MsiStockReader implements StockReaderInterface
{
    private const int DEFAULT_STOCK = 1;

    /** @var array<int, int> */
    private array $stockByWebsite = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ModuleManager $moduleManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isApplicable(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory')
            && $this->resourceConnection->getConnection()->isTableExists(
                $this->resourceConnection->getTableName('inventory_reservation')
            );
    }

    /**
     * @inheritDoc
     */
    public function read(array $productIds, int $websiteId): array
    {
        $skus = $this->skus(array_map('intval', $productIds));

        if ($skus === []) {
            return [];
        }

        $stockId = $this->stockId($websiteId);
        $indexed = $this->indexed($stockId, array_keys($skus));
        $reserved = $this->reserved($stockId, array_keys($skus));
        $thresholds = $this->thresholds(array_values($skus));
        $levels = [];

        foreach ($indexed as $sku => $row) {
            $productId = $skus[$sku];
            $minimum = $thresholds[$productId]['min_qty'] ?? 0.0;
            $salable = (float) $row['quantity'] + ($reserved[$sku] ?? 0.0) - $minimum;
            $levels[$productId] = new StockLevel(
                $productId,
                (float) $row['quantity'],
                $salable,
                (bool) $row['is_salable'] && ($salable > 0 || !($thresholds[$productId]['enforced'] ?? true)),
                $stockId
            );
        }

        return $levels;
    }

    /**
     * @param int[] $productIds
     * @return array<string, int> Sku to product id.
     */
    private function skus(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        return array_map('intval', $connection->fetchPairs(
            $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
                ->where('entity_id IN (?)', $productIds)
        ));
    }

    private function stockId(int $websiteId): int
    {
        if (!isset($this->stockByWebsite[$websiteId])) {
            $connection = $this->resourceConnection->getConnection();
            $stockId = $connection->fetchOne(
                $connection->select()
                    ->from(
                        ['c' => $this->resourceConnection->getTableName('inventory_stock_sales_channel')],
                        ['stock_id']
                    )
                    ->join(['w' => $this->resourceConnection->getTableName('store_website')], 'w.code = c.code', [])
                    ->where('c.type = ?', 'website')
                    ->where('w.website_id = ?', $websiteId)
            );
            $this->stockByWebsite[$websiteId] = $stockId === false ? self::DEFAULT_STOCK : (int) $stockId;
        }

        return $this->stockByWebsite[$websiteId];
    }

    /**
     * @param string[] $skus
     * @return array<string, array{quantity: mixed, is_salable: mixed}>
     */
    private function indexed(int $stockId, array $skus): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('inventory_stock_' . $stockId),
                    ['sku', 'quantity', 'is_salable']
                )
                ->where('sku IN (?)', $skus)
        );
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(string) $row['sku']] = ['quantity' => $row['quantity'], 'is_salable' => $row['is_salable']];
        }

        return $indexed;
    }

    /**
     * @param string[] $skus
     * @return array<string, float> Reservations are negative while an order is open.
     */
    private function reserved(int $stockId, array $skus): array
    {
        $connection = $this->resourceConnection->getConnection();
        $pairs = $connection->fetchPairs(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('inventory_reservation'),
                    ['sku', 'quantity' => new Zend_Db_Expr('SUM(quantity)')]
                )
                ->where('stock_id = ?', $stockId)
                ->where('sku IN (?)', $skus)
                ->group('sku')
        );

        return array_map('floatval', $pairs);
    }

    /**
     * @param int[] $productIds
     * @return array<int, array{min_qty: float, enforced: bool}>
     */
    private function thresholds(array $productIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('cataloginventory_stock_item'),
                    ['product_id', 'min_qty', 'use_config_min_qty', 'manage_stock', 'use_config_manage_stock',
                        'backorders', 'use_config_backorders']
                )
                ->where('product_id IN (?)', $productIds)
        );
        $thresholds = [];

        foreach ($rows as $row) {
            $manage = (bool) ($row['use_config_manage_stock'] ? $this->option('manage_stock') : $row['manage_stock']);
            $backorders = (int) ($row['use_config_backorders'] ? $this->option('backorders') : $row['backorders']);
            $thresholds[(int) $row['product_id']] = [
                'min_qty' => (float) ($row['use_config_min_qty'] ? $this->option('min_qty') : $row['min_qty']),
                'enforced' => $manage && $backorders === 0,
            ];
        }

        return $thresholds;
    }

    private function option(string $field): mixed
    {
        return $this->scopeConfig->getValue('cataloginventory/item_options/' . $field);
    }
}
