<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Mview;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Trigger;
use Magento\Framework\DB\Ddl\TriggerFactory;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Mview\Config;
use Magento\Framework\Mview\View\CollectionInterface;
use Magento\Framework\Mview\View\Subscription;
use Magento\Framework\Mview\View\SubscriptionStatementPostprocessorInterface;
use Magento\Framework\Mview\ViewInterface;

/**
 * A change-log trigger that records the entity id on both editions, resolving row_id where staging is installed.
 */
class LinkFieldSubscription extends Subscription
{
    private const string VARIABLE = '@kingletas_catalog_index_entity_id';

    /** @var string[] */
    private readonly array $ignoredColumns;

    /**
     * @param string[] $ignoredUpdateColumns
     * @param array<string, array<string, array<string, bool>>> $ignoredBySubscription
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        TriggerFactory $triggerFactory,
        CollectionInterface $viewCollection,
        ViewInterface $view,
        string $tableName,
        string $columnName,
        private readonly MetadataPool $metadataPool,
        array $ignoredUpdateColumns = [],
        array $ignoredBySubscription = [],
        ?Config $mviewConfig = null,
        ?SubscriptionStatementPostprocessorInterface $statementPostprocessor = null
    ) {
        parent::__construct(
            $resourceConnection,
            $triggerFactory,
            $viewCollection,
            $view,
            $tableName,
            $columnName,
            $ignoredUpdateColumns,
            $ignoredBySubscription,
            $mviewConfig,
            $statementPostprocessor
        );
        $this->ignoredColumns = $ignoredUpdateColumns;
    }

    /**
     * The entity whose link field the subscribed tables join on.
     */
    protected function entityInterface(): string
    {
        return ProductInterface::class;
    }

    /**
     * @inheritDoc
     */
    protected function buildStatement(string $event, ViewInterface $view): string
    {
        $metadata = $this->metadataPool->getMetadata($this->entityInterface());

        if ($metadata->getLinkField() === $metadata->getIdentifierField()) {
            return parent::buildStatement($event, $view);
        }

        $row = $event === Trigger::EVENT_DELETE ? 'OLD' : 'NEW';
        $link = $this->connection->quoteIdentifier($metadata->getLinkField());
        $changelog = $view->getChangelog();
        $insert = sprintf(
            'IF (%1$s IS NOT NULL) THEN INSERT INTO %2$s (%3$s) VALUES (%1$s); END IF;',
            self::VARIABLE,
            $this->connection->quoteIdentifier($this->resourceConnection->getTableName($changelog->getName())),
            $this->connection->quoteIdentifier($changelog->getColumnName())
        );

        if ($event === Trigger::EVENT_UPDATE) {
            $insert = $this->whenChanged($insert);
        }

        return sprintf(
            'SET %1$s = (SELECT %2$s FROM %3$s WHERE %4$s = %5$s.%4$s LIMIT 1);',
            self::VARIABLE,
            $this->connection->quoteIdentifier($metadata->getIdentifierField()),
            $this->connection->quoteIdentifier($this->resourceConnection->getTableName($metadata->getEntityTable())),
            $link,
            $row
        ) . $insert;
    }

    private function whenChanged(string $statement): string
    {
        $table = $this->resourceConnection->getTableName($this->getTableName());

        if (!$this->connection->isTableExists($table)) {
            return $statement;
        }

        $conditions = [];

        $columns = array_diff(array_keys($this->connection->describeTable($table)), $this->ignoredColumns);

        foreach ($columns as $column) {
            $quoted = $this->connection->quoteIdentifier((string) $column);
            $conditions[] = sprintf('NOT(NEW.%1$s <=> OLD.%1$s)', $quoted);
        }

        if ($conditions === []) {
            return $statement;
        }

        return sprintf('IF (%s) THEN %s END IF;', implode(' OR ', $conditions), $statement);
    }
}
