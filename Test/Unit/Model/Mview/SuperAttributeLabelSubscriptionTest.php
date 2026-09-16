<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Mview;

use Kingletas\CatalogIndex\Model\Mview\SuperAttributeLabelSubscription;
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

class SuperAttributeLabelSubscriptionTest extends TestCase
{
    use StubbedDatabase;

    public function testALabelFindsItsProductThroughItsSuperAttribute(): void
    {
        $statement = $this->build(Trigger::EVENT_INSERT, 'row_id');

        $this->assertStringContainsString(
            'SET @kingletas_catalog_index_entity_id = (SELECT entity.`entity_id` '
                . 'FROM `catalog_product_entity` AS entity '
                . 'INNER JOIN `catalog_product_super_attribute` AS super_attribute '
                . 'ON entity.`row_id` = super_attribute.`product_id` '
                . 'WHERE super_attribute.`product_super_attribute_id` = NEW.`product_super_attribute_id` LIMIT 1);',
            $statement
        );
    }

    /**
     * A label row carries no product id on either edition, so the lookup runs without staging too.
     */
    public function testAnUnstagedStoreStillLooksTheProductUp(): void
    {
        $lookup = $this->subscription('entity_id')->lookup('OLD');

        $this->assertStringContainsString('ON entity.`entity_id` = super_attribute.`product_id`', (string) $lookup);
        $this->assertStringContainsString('= OLD.`product_super_attribute_id`', (string) $lookup);
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

    private function subscription(string $linkField, ?ViewInterface $view = null): SuperAttributeLabelSubscription
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
            'catalog_product_super_attribute_label',
            'product_super_attribute_id',
            $pool,
            [],
            [],
            $this->createMock(Config::class),
            $this->createMock(SubscriptionStatementPostprocessorInterface::class)
        ) extends SuperAttributeLabelSubscription {
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
