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
use Magento\Framework\EntityManager\EntityMetadataInterface;
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
        $lookup = $this->entityIdLookup($event === Trigger::EVENT_DELETE ? 'OLD' : 'NEW');

        if ($lookup === null) {
            return parent::buildStatement($event, $view);
        }

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

        return sprintf('SET %s = (%s);', self::VARIABLE, $lookup) . $insert;
    }

    /**
     * The query that finds the changed row's entity id, or null when the subscribed column already holds it.
     */
    protected function entityIdLookup(string $row): ?string
    {
        $metadata = $this->metadata();

        if ($metadata->getLinkField() === $metadata->getIdentifierField()) {
            return null;
        }

        return $this->entityIdByLinkValue($row . '.' . $this->connection->quoteIdentifier($metadata->getLinkField()));
    }

    protected function entityIdByLinkValue(string $linkValue): string
    {
        $metadata = $this->metadata();

        return sprintf(
            'SELECT %s FROM %s WHERE %s = %s LIMIT 1',
            $this->connection->quoteIdentifier($metadata->getIdentifierField()),
            $this->connection->quoteIdentifier($this->resourceConnection->getTableName($metadata->getEntityTable())),
            $this->connection->quoteIdentifier($metadata->getLinkField()),
            $linkValue
        );
    }

    protected function metadata(): EntityMetadataInterface
    {
        return $this->metadataPool->getMetadata($this->entityInterface());
    }

    protected function table(string $name): string
    {
        return $this->connection->quoteIdentifier($this->resourceConnection->getTableName($name));
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
