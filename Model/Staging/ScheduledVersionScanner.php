<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Staging;

use Magento\Framework\App\ResourceConnection;

/**
 * Finds entities whose staged version started or ended between two moments, which no trigger ever records.
 */
class ScheduledVersionScanner
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return int[]
     */
    public function products(int $from, int $until): array
    {
        return $this->between('catalog_product_entity', $from, $until);
    }

    /**
     * @return int[]
     */
    public function categories(int $from, int $until): array
    {
        return $this->between('catalog_category_entity', $from, $until);
    }

    /**
     * @return int[]
     */
    private function between(string $table, int $from, int $until): array
    {
        if ($until <= $from) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName($table);

        if (!$connection->tableColumnExists($table, 'created_in')) {
            return [];
        }

        $started = $connection->select()
            ->from($table, ['entity_id'])
            ->where('created_in > ?', $from)
            ->where('created_in <= ?', $until);
        $ended = $connection->select()
            ->from($table, ['entity_id'])
            ->where('updated_in > ?', $from)
            ->where('updated_in <= ?', $until);
        $union = $connection->select()->union([$started, $ended]);
        $ids = $connection->fetchCol($union->assemble());

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
