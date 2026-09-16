<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Read\CollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use PHPUnit\Framework\TestCase;

class CollectionHydratorTest extends TestCase
{
    use ProductDoubles;

    /** @var array<int, array{0: string, 1: string, 2: int}> */
    private array $recorded = [];

    /** @var mixed[] */
    private array $flags = [];

    public function testEveryItemIsFilledAndTheGalleryQueryIsSkipped(): void
    {
        $items = [$this->product(['entity_id' => 5]), $this->product(['entity_id' => 6])];

        $served = $this->hydrator([5 => $this->view(5), 6 => $this->view(6)])->hydrate(
            $this->collection($items),
            PageType::CategoryListing
        );

        $this->assertTrue($served);
        $this->assertSame('name-6', $items[1]->getData('name'));
        $this->assertSame(['media_gallery_added' => true], $this->flags);
        $this->assertSame([['served', 'category_listing', 2]], $this->recorded);
    }

    /**
     * Mixing document and database values on one page would show two versions of the catalog side by side.
     */
    public function testOneMissingDocumentSendsTheWholePageToTheDatabase(): void
    {
        $items = [$this->product(['entity_id' => 5]), $this->product(['entity_id' => 6])];

        $served = $this->hydrator([5 => $this->view(5)])->hydrate($this->collection($items), PageType::CategoryListing);

        $this->assertFalse($served);
        $this->assertFalse($items[0]->hasData('name'));
        $this->assertSame([['fallback', 'missing_document', 2]], $this->recorded);
    }

    public function testAStoreErrorFallsBackAndSaysWhy(): void
    {
        $served = $this->hydrator(null)->hydrate(
            $this->collection([$this->product(['entity_id' => 5])]),
            PageType::Widget
        );

        $this->assertFalse($served);
        $this->assertSame([['fallback', 'store_error', 1]], $this->recorded);
    }

    /**
     * @param array<int, ProductView>|null $views Null makes the store fail.
     */
    private function hydrator(?array $views): CollectionHydrator
    {
        $reader = $this->createMock(DocumentReaderInterface::class);
        $reader->method('products')->willReturnCallback(static function () use ($views): array {
            return $views ?? throw new DocumentStoreException('down');
        });
        $contexts = $this->createMock(ReadContextResolver::class);
        $contexts->method('resolve')->willReturnCallback(
            static fn (PageType $page): ReadContext => new ReadContext($page, 1, 1, 0)
        );
        $hydrator = $this->createMock(ProductHydrator::class);
        $hydrator->method('fillListingItem')->willReturnCallback(static function ($item, ProductView $view): void {
            $item->setData('name', $view->attributes()['name']);
        });
        $recorder = $this->createMock(FallbackRecorder::class);
        $recorder->method('served')->willReturnCallback(function (PageType $page, int $count): void {
            $this->recorded[] = ['served', $page->value, $count];
        });
        $recorder->method('fellBack')->willReturnCallback(function (PageType $page, string $reason, int $count): void {
            $this->recorded[] = ['fallback', $reason, $count];
        });

        return new CollectionHydrator($reader, $contexts, $hydrator, $recorder);
    }

    /**
     * @param \Magento\Catalog\Model\Product[] $items
     */
    private function collection(array $items): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturn($items);
        $collection->method('getStoreId')->willReturn(1);
        $collection->method('setFlag')->willReturnCallback(function (
            string $flag,
            mixed $value
        ) use ($collection): Collection {
            $this->flags[$flag] = $value;

            return $collection;
        });

        return $collection;
    }

    private function view(int $id): ProductView
    {
        return new ProductView($id, ['listing_attributes' => ['name' => 'name-' . $id]]);
    }
}
