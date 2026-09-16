<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Schedule;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Expr;

/**
 * Remembers when documents must be rebuilt because a dated value starts or stops applying.
 */
class ScheduleStorage
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly string $table = 'kingletas_catalog_index_schedule'
    ) {
    }

    /**
     * @param array<int, array<int, DateTimeImmutable>> $momentsById
     */
    public function record(IndexFamily $family, int $scopeId, array $momentsById): void
    {
        $rows = [];

        foreach ($momentsById as $entityId => $moments) {
            foreach ($moments as $moment) {
                $rows[] = [
                    'family' => $family->value,
                    'entity_id' => (int) $entityId,
                    'scope_id' => $scopeId,
                    'due_at' => $moment->getTimestamp(),
                ];
            }
        }

        if ($rows !== []) {
            $this->resourceConnection->getConnection()->insertOnDuplicate($this->table(), $rows, ['due_at']);
        }
    }

    /**
     * @return ScheduledRefresh[] Oldest first.
     */
    public function due(int $now, int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->table())
                ->where('due_at <= ?', $now)
                ->order(['due_at ASC', 'schedule_id ASC'])
                ->limit($limit)
        );
        $due = [];

        foreach ($rows as $row) {
            $family = IndexFamily::tryFrom((string) $row['family']);

            if ($family !== null) {
                $due[] = new ScheduledRefresh(
                    (int) $row['schedule_id'],
                    $family,
                    (int) $row['entity_id'],
                    (int) $row['scope_id'],
                    (int) $row['due_at']
                );
            }
        }

        return $due;
    }

    /**
     * @param int[] $scheduleIds
     */
    public function remove(array $scheduleIds): void
    {
        if ($scheduleIds !== []) {
            $this->resourceConnection->getConnection()->delete($this->table(), ['schedule_id IN (?)' => $scheduleIds]);
        }
    }

    public function pending(): int
    {
        $connection = $this->resourceConnection->getConnection();

        return (int) $connection->fetchOne(
            $connection->select()->from($this->table(), [new Zend_Db_Expr('COUNT(*)')])
        );
    }

    private function table(): string
    {
        return $this->resourceConnection->getTableName($this->table);
    }
}
