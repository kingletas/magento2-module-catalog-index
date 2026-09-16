<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Mview;

use Kingletas\CatalogIndex\Model\Mview\SuperAttributeSubscription;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\DB\Ddl\Trigger;
use Magento\Framework\DB\Ddl\TriggerFactory;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Mview\Config;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\CollectionInterface;
use Magento\Framework\Mview\View\SubscriptionStatementPostprocessorInterface;
use Magento\Framework\Mview\ViewInterface;
use PHPUnit\Framework\TestCase;

class SuperAttributeSubscriptionTest extends TestCase
{
    use StubbedDatabase;

    public function testAStagedStoreResolvesTheEntityIdFromTheProductLinkColumn(): void
    {
        $statement = $this->build(Trigger::EVENT_INSERT, 'row_id');

        $this->assertStringContainsString(
            'SET @kingletas_catalog_index_entity_id = (SELECT `entity_id` FROM `catalog_product_entity` '
                . 'WHERE `row_id` = NEW.`product_id` LIMIT 1);',
            $statement
        );
    }

    public function testADeleteReadsTheOldRow(): void
    {
        $this->assertStringContainsString('= OLD.`product_id`', $this->build(Trigger::EVENT_DELETE, 'row_id'));
    }

    /**
     * Without staging the product column already holds the entity id, so no lookup is needed.
     */
    public function testAnUnstagedStoreNeedsNoLookup(): void
    {
        $this->assertNull($this->subscription('entity_id')->lookup('NEW'));
    }

    private function build(string $event, string $linkField): string
    {
        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('kingletas_catalog_index_product_cl');
        $changelog->method('getColumnName')->willReturn('entity_id');
        $view = $this->createMock(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);

        return $this->subscription($linkField, $view)->statement($event, $view);
    }

    private function subscription(string $linkField, ?ViewInterface $view = null): SuperAttributeSubscription
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn($linkField);
        $metadata->method('getIdentifierField')->willReturn('entity_id');
        $metadata->method('getEntityTable')->willReturn('catalog_product_entity');
        $pool = $this->createMock(MetadataPool::class);
        $pool->method('getMetadata')->willReturn($metadata);
        $resource = $this->resourceConnection();
        $resource->getConnection()->method('describeTable')->willReturn(['value' => []]);

        return new class (
            $resource,
            $this->createMock(TriggerFactory::class),
            $this->createMock(CollectionInterface::class),
            $view ?? $this->createMock(ViewInterface::class),
            'catalog_product_super_attribute',
            'product_id',
            $pool,
            [],
            [],
            $this->createMock(Config::class),
            $this->createMock(SubscriptionStatementPostprocessorInterface::class)
        ) extends SuperAttributeSubscription {
            public function statement(string $event, ViewInterface $view): string
            {
                return $this->buildStatement($event, $view);
            }

            public function lookup(string $row): ?string
            {
                return $this->entityIdLookup($row);
            }
        };
    }
}
