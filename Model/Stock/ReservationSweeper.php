<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Stock;

use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Expr;

/**
 * Picks up reservations placed by any path since the last sweep, including orders no observer saw.
 */
class ReservationSweeper
{
    private const string WATERMARK = 'watermark:reservation';

    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly StateStorage $state,
        private readonly RefreshPublisher $publisher,
        private readonly int $limit = 10000
    ) {
    }

    /**
     * @return int How many products were sent for a stock refresh.
     */
    public function sweep(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('inventory_reservation');

        if (!$this->config->isEnabled() || !$connection->isTableExists($table)) {
            return 0;
        }

        $watermark = $this->state->get(self::WATERMARK);

        if ($watermark === null) {
            $latest = (int) $connection->fetchOne(
                $connection->select()->from($table, [new Zend_Db_Expr('MAX(reservation_id)')])
            );
            $this->state->set(self::WATERMARK, ['reservation_id' => $latest]);

            return 0;
        }

        $after = (int) ($watermark['reservation_id'] ?? 0);
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['reservation_id', 'sku'])
                ->where('reservation_id > ?', $after)
                ->order('reservation_id ASC')
                ->limit($this->limit)
        );

        if ($rows === []) {
            return 0;
        }

        $productIds = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('sku IN (?)', array_values(array_unique($rows)))
        ));

        if ($productIds !== []) {
            $this->publisher->publish(IndexFamily::Stock, $productIds, RefreshRequest::REASON_RESERVATION);
        }

        $this->state->set(self::WATERMARK, ['reservation_id' => (int) array_key_last($rows)]);

        return count($productIds);
    }
}
