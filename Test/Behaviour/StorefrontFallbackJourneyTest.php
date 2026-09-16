<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Behaviour;

use Kingletas\CatalogIndex\Model\Build\StoredAttributes;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Model\Read\CollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\DocumentAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\DocumentReader;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Kingletas\CatalogIndex\Observer\Read\FillDocumentAttributes;
use Kingletas\CatalogIndex\Observer\Read\SkipDocumentAttributes;
use Kingletas\CatalogIndex\Plugin\Layer\MarkCategoryListing;
use Kingletas\Foundation\Test\Support\ArrayCache;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\CollectionFilterInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * A category page reads documents, the store goes away, and pages keep rendering from the database.
 */
class StorefrontFallbackJourneyTest extends TestCase
{
    use LinkFieldDouble;

    use ProductDoubles;
    use ShippedConfig;

    private InMemoryDocumentStore $store;

    private ArrayCache $cache;

    private FakeClock $clock;

    private int $databaseLoads = 0;

    protected function setUp(): void
    {
        $this->store = new InMemoryDocumentStore();
        $this->cache = new ArrayCache();
        $this->clock = new FakeClock();

        foreach ([5, 6] as $id) {
            $this->store->seed('kingletas_catalog_product_1', (string) $id, [
                'type_id' => 'simple',
                'listing_attributes' => ['name' => 'Product ' . $id],
            ]);
        }
    }

    public function testACategoryPageIsServedFromDocumentsUntilTheStoreFailsThenTheBreakerStopsAsking(): void
    {
        $page = $this->renderCategoryPage();
        $this->assertSame('Product 6', $page[1]->getData('name'));
        $this->assertSame(0, $this->databaseLoads);

        $this->store->unreachable = true;

        for ($request = 0; $request < 4; $request++) {
            $this->renderCategoryPage();
        }

        $fetchesWhileDown = $this->store->calls('fetch');
        $this->renderCategoryPage();

        $this->assertSame($fetchesWhileDown, $this->store->calls('fetch'));
        $this->assertSame(5, $this->databaseLoads);
        $this->assertSame(
            ['served' => 2, 'fallback' => ['store_error' => 6, 'breaker_open' => 4]],
            (new MetricStorage($this->cache, new Json(), $this->clock))->totals(1)['category_listing']
        );

        $this->store->unreachable = false;
        $this->clock->advance('+31 seconds');
        $this->renderCategoryPage();
        $this->assertSame(5, $this->databaseLoads);
    }

    public function testAProductMissingItsDocumentSendsThatPageToTheDatabase(): void
    {
        unset($this->store->indexes['kingletas_catalog_product_1']['6']);

        $page = $this->renderCategoryPage();

        $this->assertSame(1, $this->databaseLoads);
        $this->assertFalse($page[0]->hasData('name'));
    }

    /**
     * @return Product[]
     */
    private function renderCategoryPage(): array
    {
        $config = $this->config(['read/category_listing' => '1', 'read/breaker_failures' => '3']);
        $scope = new PageScope();
        $items = [$this->product(['entity_id' => 5]), $this->product(['entity_id' => 6])];
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getStoreId')->willReturn(1);
        $selected = ['name' => true];
        $collection->method('isAttributeAdded')->willReturnCallback(
            static function (string $code) use (&$selected): bool {
                return $selected[$code] ?? false;
            }
        );
        $collection->method('removeAttributeToSelect')->willReturnCallback(
            static function (string $code) use (&$selected, $collection): Collection {
                $selected[$code] = false;

                return $collection;
            }
        );
        $collection->method('_loadAttributes')->willReturnCallback(function () use ($collection): Collection {
            $this->databaseLoads++;

            return $collection;
        });

        (new MarkCategoryListing($scope))->beforeFilter(
            $this->createMock(CollectionFilterInterface::class),
            $collection,
            $this->createMock(Category::class)
        );

        $breaker = new CircuitBreaker($this->cache, $config, $this->clock);
        $recorder = new FallbackRecorder(new MetricStorage($this->cache, new Json(), $this->clock));
        $contexts = $this->createMock(ReadContextResolver::class);
        $contexts->method('resolve')->willReturn(new ReadContext(PageType::CategoryListing, 1, 1, 0));
        $gate = new ReadGate($config, $breaker);
        $hydrator = new CollectionHydrator(
            new DocumentReader($this->store, new IndexNamer($config), $breaker),
            $contexts,
            new ProductHydrator(
                $config,
                new ServedOptions(),
                $this->linkField(),
                new ServedAttributes()
            ),
            $recorder
        );
        $event = new Observer(['event' => new Event(['collection' => $collection])]);

        (new SkipDocumentAttributes($scope, $gate, $this->attributeCodes()))->execute($event);

        if ($selected['name']) {
            $this->databaseLoads++;
        }

        (new FillDocumentAttributes($scope, $hydrator, $recorder))->execute($event);
        $recorder->flush();

        return $items;
    }

    private function attributeCodes(): DocumentAttributeCodes
    {
        $name = $this->createMock(AbstractAttribute::class);
        $name->method('isStatic')->willReturn(false);
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getEntityAttributes')->willReturn(['name' => $name]);

        return new DocumentAttributeCodes($eavConfig, new StoredAttributes());
    }
}
