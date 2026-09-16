<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Observer\Read;

use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Read\CategoryAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\CategoryCollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\CategoryHydrator;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Kingletas\CatalogIndex\Observer\Read\FillDocumentCategoryAttributes;
use Kingletas\CatalogIndex\Observer\Read\SkipDocumentCategoryAttributes;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\Flat\Collection as FlatCollection;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryTreeObserversTest extends TestCase
{
    use ProductDoubles;

    private PageScope $scope;

    /** @var string[] */
    private array $calls = [];

    /** @var string[] */
    private array $fallbacks = [];

    /** @var Category[] */
    private array $items = [];

    protected function setUp(): void
    {
        $this->scope = new PageScope();
        $this->items = [$this->category(['entity_id' => 12]), $this->category(['entity_id' => 18])];
    }

    public function testAMenuTakesItsNamesAndCountsFromDocuments(): void
    {
        $collection = $this->collection();

        $this->skip()->execute($this->observer($collection));
        $this->fill($collection, served: true)->execute($this->observer($collection));

        $this->assertSame(['remove name', 'remove is_active', 'add name is_active'], $this->calls);
        $this->assertSame('Jackets', $this->items[0]->getData('name'));
        $this->assertSame(28, $this->items[0]->getData('product_count'));
        $this->assertSame([], $this->fallbacks);
    }

    public function testAMissingCategoryDocumentSendsTheWholeListToTheDatabase(): void
    {
        $collection = $this->collection();

        $this->skip()->execute($this->observer($collection));
        $this->fill($collection, served: false)->execute($this->observer($collection));

        $this->assertSame(
            ['remove name', 'remove is_active', 'add name is_active', 'load attributes'],
            $this->calls
        );
        $this->assertSame(['missing_document x2'], $this->fallbacks);
    }

    public function testAStoreErrorSendsTheWholeListToTheDatabase(): void
    {
        $collection = $this->collection();

        $this->skip()->execute($this->observer($collection));
        $this->fill($collection, served: false, failing: true)->execute($this->observer($collection));

        $this->assertContains('load attributes', $this->calls);
        $this->assertSame(['store_error x2'], $this->fallbacks);
    }

    public function testTheSwitchBeingOffLeavesEveryCategoryListAlone(): void
    {
        $collection = $this->collection();

        $this->skip(ReadDecision::Disabled)->execute($this->observer($collection));
        $this->fill($collection, served: true, decision: ReadDecision::Disabled)
            ->execute($this->observer($collection));

        $this->assertSame([], $this->calls);
        $this->assertSame([], $this->fallbacks);
    }

    public function testAnOpenBreakerCountsEveryCategoryItLoaded(): void
    {
        $collection = $this->collection();

        $this->skip(ReadDecision::BreakerOpen)->execute($this->observer($collection));
        $this->fill($collection, served: true, decision: ReadDecision::BreakerOpen)
            ->execute($this->observer($collection));

        $this->assertSame([], $this->calls);
        $this->assertSame(['breaker_open x2'], $this->fallbacks);
    }

    public function testTheFlatResourceSkipsNoAttributeAndStillGetsItsCounts(): void
    {
        $collection = $this->flatCollection();

        $this->skip()->execute($this->observer($collection));
        $this->fill($collection, served: true)->execute($this->observer($collection));

        $this->assertSame([], $this->calls);
        $this->assertSame('Jackets', $this->items[0]->getData('name'));
        $this->assertSame(28, $this->items[0]->getData('product_count'));
        $this->assertSame([], $this->fallbacks);
    }

    private function skip(ReadDecision $decision = ReadDecision::Allow): SkipDocumentCategoryAttributes
    {
        $gate = $this->createMock(ReadGate::class);
        $gate->method('decide')->willReturn($decision);
        $codes = $this->createMock(CategoryAttributeCodes::class);
        $codes->method('selectedOn')->willReturn(['name', 'is_active']);

        return new SkipDocumentCategoryAttributes($this->scope, $gate, $codes);
    }

    private function fill(
        Collection|FlatCollection $collection,
        bool $served,
        bool $failing = false,
        ReadDecision $decision = ReadDecision::Allow
    ): FillDocumentCategoryAttributes {
        $reader = $this->createMock(DocumentReaderInterface::class);
        $reader->method('categories')->willReturnCallback(function () use ($served, $failing): array {
            if ($failing) {
                throw new DocumentStoreException('The document store did not answer.');
            }

            return $served
                ? [
                    12 => new CategoryView(12, ['attributes' => ['name' => 'Jackets'], 'product_count' => 28]),
                    18 => new CategoryView(18, ['attributes' => ['name' => 'Coats'], 'product_count' => 4]),
                ]
                : [12 => new CategoryView(12, ['attributes' => ['name' => 'Jackets']])];
        });
        $contexts = $this->createMock(ReadContextResolver::class);
        $contexts->method('resolve')->willReturn(new ReadContext(PageType::CategoryTree, 1, 1, 0));
        $recorder = $this->createMock(FallbackRecorder::class);
        $recorder->method('fellBack')->willReturnCallback(
            function (PageType $page, string $reason, int $count): void {
                $this->fallbacks[] = $reason . ' x' . $count;
            }
        );

        $gate = $this->createMock(ReadGate::class);
        $gate->method('decide')->willReturn($decision);

        return new FillDocumentCategoryAttributes(
            $this->scope,
            $gate,
            new CategoryCollectionHydrator($reader, $contexts, new CategoryHydrator(), $recorder),
            $recorder
        );
    }

    private function collection(): Collection&MockObject
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getStoreId')->willReturn(1);
        $collection->method('getItems')->willReturn($this->items);
        $collection->method('removeAttributeToSelect')->willReturnCallback(function (string $code) use ($collection) {
            $this->calls[] = 'remove ' . $code;

            return $collection;
        });
        $collection->method('addAttributeToSelect')->willReturnCallback(function (array $codes) use ($collection) {
            $this->calls[] = 'add ' . implode(' ', $codes);

            return $collection;
        });
        $collection->method('_loadAttributes')->willReturnCallback(function () use ($collection) {
            $this->calls[] = 'load attributes';

            return $collection;
        });

        return $collection;
    }

    private function flatCollection(): FlatCollection&MockObject
    {
        $collection = $this->createMock(FlatCollection::class);
        $collection->method('getStoreId')->willReturn(1);
        $collection->method('getItems')->willReturn($this->items);

        return $collection;
    }

    private function observer(Collection|FlatCollection $collection): Observer
    {
        return new Observer(['event' => new Event(['category_collection' => $collection])]);
    }
}
