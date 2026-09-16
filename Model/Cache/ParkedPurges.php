<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

use Magento\Framework\App\ResourceConnection;

/**
 * Cache tags waiting for the purge lock, stored as rows so a tag parked during a flush is never deleted by it.
 */
class ParkedPurges
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly string $table = 'kingletas_catalog_index_parked_purge'
    ) {
    }

    /**
     * @param string[] $tags
     */
    public function park(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $this->resourceConnection->getConnection()->insertMultiple(
            $this->tableName(),
            array_map(static fn (string $tag): array => ['tag' => $tag], array_values($tags))
        );
    }

    /**
     * @return array{0: int, 1: string[]} The highest row id read, and the tags up to it.
     */
    public function oldest(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->tableName(), ['purge_id', 'tag'])
                ->order('purge_id ASC')
                ->limit(max(1, $limit))
        );

        if ($rows === []) {
            return [0, []];
        }

        return [(int) max(array_keys($rows)), array_values(array_unique(array_map('strval', $rows)))];
    }

    public function release(int $upToId): void
    {
        if ($upToId <= 0) {
            return;
        }

        $this->resourceConnection->getConnection()->delete($this->tableName(), ['purge_id <= ?' => $upToId]);
    }

    public function count(): int
    {
        $connection = $this->resourceConnection->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($this->tableName(), ['COUNT(*)'])
        );
    }

    private function tableName(): string
    {
        return $this->resourceConnection->getTableName($this->table);
    }
}
