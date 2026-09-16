<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Mview;

use Magento\Catalog\Api\Data\CategoryInterface;
use Kingletas\CatalogIndex\Model\Mview\CategoryLinkFieldSubscription;
use Kingletas\CatalogIndex\Model\Mview\LinkFieldSubscription;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\DB\Ddl\Trigger;
use Magento\Framework\DB\Ddl\TriggerFactory;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Mview\Config;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\CollectionInterface;
use Magento\Framework\Mview\View\SubscriptionInterface;
use Magento\Framework\Mview\View\SubscriptionStatementPostprocessorInterface;
use Magento\Framework\Mview\ViewInterface;
use PHPUnit\Framework\TestCase;

class LinkFieldSubscriptionTest extends TestCase
{
    use StubbedDatabase;

    /**
     * Staged attribute rows carry row_id, so the trigger resolves the entity id before writing the change log.
     */
    public function testAStagedTableResolvesTheEntityIdBeforeLogging(): void
    {
        $statement = $this->build(Trigger::EVENT_INSERT);

        $this->assertStringContainsString(
            'SET @kingletas_catalog_index_entity_id = (SELECT `entity_id` FROM `catalog_product_entity` '
                . 'WHERE `row_id` = NEW.`row_id` LIMIT 1);',
            $statement
        );
        $this->assertStringContainsString('INSERT INTO `kingletas_catalog_index_product_cl` (`entity_id`)', $statement);
    }

    public function testADeleteReadsTheOldRowAndAnUpdateOnlyLogsRealChanges(): void
    {
        $this->assertStringContainsString('= OLD.`row_id`', $this->build(Trigger::EVENT_DELETE));

        $update = $this->build(Trigger::EVENT_UPDATE);

        $this->assertStringContainsString('NOT(NEW.`value` <=> OLD.`value`)', $update);
        $this->assertStringNotContainsString('updated_at', $update);
    }

    /**
     * Magento's mview config calls class_implements() on the subscription model, so a virtual type there is refused.
     */
    public function testEverySubscriptionModelNamedInMviewIsARealSubscriptionClass(): void
    {
        $mview = simplexml_load_file(dirname(__DIR__, 4) . '/etc/mview.xml');
        $models = [];

        foreach ($mview->xpath('//table[@subscription_model]') ?: [] as $table) {
            $models[(string) $table['subscription_model']] = true;
        }

        $this->assertNotEmpty($models, 'No subscription model is declared in mview.xml.');

        foreach (array_keys($models) as $model) {
            $this->assertTrue(class_exists($model), $model . ' is not a class; mview cannot use a virtual type.');
            $this->assertContains(SubscriptionInterface::class, class_implements($model) ?: []);
        }
    }

    public function testACategorySubscriptionResolvesTheCategorysOwnLinkField(): void
    {
        $pool = $this->createMock(MetadataPool::class);
        $pool->expects($this->once())
            ->method('getMetadata')
            ->with(CategoryInterface::class)
            ->willReturn($this->createMock(EntityMetadataInterface::class));

        $subscription = new class (
            $this->resourceConnection(),
            $this->createMock(TriggerFactory::class),
            $this->createMock(CollectionInterface::class),
            $this->createMock(ViewInterface::class),
            'catalog_category_entity_varchar',
            'entity_id',
            $pool,
            [],
            [],
            $this->createMock(Config::class),
            $this->createMock(SubscriptionStatementPostprocessorInterface::class)
        ) extends CategoryLinkFieldSubscription {
            public function entity(): string
            {
                return $this->entityInterface();
            }
        };

        $this->assertSame(CategoryInterface::class, $subscription->entity());
        $pool->getMetadata($subscription->entity());
    }

    private function build(string $event): string
    {
        $metadata = $this->createMock(EntityMetadataInterface::class);
        $metadata->method('getLinkField')->willReturn('row_id');
        $metadata->method('getIdentifierField')->willReturn('entity_id');
        $metadata->method('getEntityTable')->willReturn('catalog_product_entity');
        $pool = $this->createMock(MetadataPool::class);
        $pool->method('getMetadata')->willReturn($metadata);
        $resource = $this->resourceConnection();
        $resource->getConnection()->method('describeTable')->willReturn(['value' => [], 'updated_at' => []]);
        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getName')->willReturn('kingletas_catalog_index_product_cl');
        $changelog->method('getColumnName')->willReturn('entity_id');
        $view = $this->createMock(ViewInterface::class);
        $view->method('getChangelog')->willReturn($changelog);

        $subscription = new class (
            $resource,
            $this->createMock(TriggerFactory::class),
            $this->createMock(CollectionInterface::class),
            $view,
            'catalog_product_entity_int',
            'entity_id',
            $pool,
            ['updated_at'],
            [],
            $this->createMock(Config::class),
            $this->createMock(SubscriptionStatementPostprocessorInterface::class)
        ) extends LinkFieldSubscription {
            public function statement(string $event, ViewInterface $view): string
            {
                return $this->buildStatement($event, $view);
            }
        };

        return $subscription->statement($event, $view);
    }
}
