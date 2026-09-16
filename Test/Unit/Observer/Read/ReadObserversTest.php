<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\CollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\DocumentAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Kingletas\CatalogIndex\Observer\Read\EnterEntityPage;
use Kingletas\CatalogIndex\Observer\Read\FillDocumentAttributes;
use Kingletas\CatalogIndex\Observer\Read\LeaveEntityPage;
use Kingletas\CatalogIndex\Observer\Read\SkipDocumentAttributes;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Action\AbstractAction;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReadObserversTest extends TestCase
{
    private PageScope $scope;

    /** @var string[] What happened to the collection, in order. */
    private array $calls = [];

    /** @var string[] */
    private array $fallbacks = [];

    protected function setUp(): void
    {
        $this->scope = new PageScope();
    }

    public function testTheProductPageIsEnteredForItsOwnActionAndLeftAfterItsProductLoads(): void
    {
        $enter = new EnterEntityPage($this->scope, 'product_view', ['catalog_product_view']);

        $enter->execute($this->observer(['controller_action' => $this->action('catalog_product_view', 5)]));
        $this->assertTrue($this->scope->targets(PageType::ProductView, 5));

        (new LeaveEntityPage($this->scope, 'product_view'))->execute($this->observer([]));
        $this->assertFalse($this->scope->targets(PageType::ProductView, 5));

        $enter->execute($this->observer(['controller_action' => $this->action('wishlist_index_configure', 5)]));
        $this->assertFalse($this->scope->targets(PageType::ProductView, 5));
    }

    public function testTheCategoryPageIsEnteredFromThePredispatchRequest(): void
    {
        $request = $this->request('catalog_category_view', 12);

        (new EnterEntityPage($this->scope, 'category_view', ['catalog_category_view']))
            ->execute($this->observer(['request' => $request]));

        $this->assertTrue($this->scope->targets(PageType::CategoryView, 12));
    }

    public function testAMarkedCollectionSkipsWhatDocumentsSupplyAndFillsThem(): void
    {
        $collection = $this->collection();
        $this->scope->mark($collection, PageType::CategoryListing);

        $this->skip()->execute($this->observer(['collection' => $collection]));
        $this->fill(true)->execute($this->observer(['collection' => $collection]));

        $this->assertSame(['remove name', 'remove media_gallery', 'add name media_gallery', 'hydrate'], $this->calls);
    }

    public function testWhenDocumentsFailTheSkippedAttributesAreLoadedFromTheDatabase(): void
    {
        $collection = $this->collection();
        $this->scope->mark($collection, PageType::CategoryListing);

        $this->skip()->execute($this->observer(['collection' => $collection]));
        $this->fill(false)->execute($this->observer(['collection' => $collection]));

        $this->assertSame(
            ['remove name', 'remove media_gallery', 'add name media_gallery', 'hydrate', 'load attributes', 'gallery'],
            $this->calls
        );
    }

    public function testAnUnmarkedFlatOrDisabledCollectionIsLeftAlone(): void
    {
        $this->skip()->execute($this->observer(['collection' => $this->collection()]));

        $flat = $this->collection(true);
        $this->scope->mark($flat, PageType::CategoryListing);
        $this->skip()->execute($this->observer(['collection' => $flat]));

        $disabled = $this->collection();
        $this->scope->mark($disabled, PageType::CategoryListing);
        $this->skip(ReadDecision::Disabled)->execute($this->observer(['collection' => $disabled]));
        $this->fill(true)->execute($this->observer(['collection' => $disabled]));

        $this->assertSame([], $this->calls);
    }

    public function testAnOpenBreakerSkipsNothingAndCountsEveryProductLoaded(): void
    {
        $collection = $this->collection();
        $this->scope->mark($collection, PageType::CategoryListing);

        $this->skip(ReadDecision::BreakerOpen)->execute($this->observer(['collection' => $collection]));
        $this->fill(true)->execute($this->observer(['collection' => $collection]));

        $this->assertSame([], $this->calls);
        $this->assertSame(['breaker_open x3'], $this->fallbacks);
    }

    private function skip(ReadDecision $decision = ReadDecision::Allow): SkipDocumentAttributes
    {
        $gate = $this->createMock(ReadGate::class);
        $gate->method('decide')->willReturn($decision);
        $codes = $this->createMock(DocumentAttributeCodes::class);
        $codes->method('selectedOn')->willReturn(['name', 'media_gallery']);

        return new SkipDocumentAttributes($this->scope, $gate, $codes);
    }

    private function fill(bool $hydrates): FillDocumentAttributes
    {
        $hydrator = $this->createMock(CollectionHydrator::class);
        $hydrator->method('hydrate')->willReturnCallback(function () use ($hydrates): bool {
            $this->calls[] = 'hydrate';

            return $hydrates;
        });
        $recorder = $this->createMock(FallbackRecorder::class);
        $recorder->method('fellBack')->willReturnCallback(
            function (PageType $page, string $reason, int $count): void {
                $this->fallbacks[] = $reason . ' x' . $count;
            }
        );

        return new FillDocumentAttributes($this->scope, $hydrator, $recorder);
    }

    private function collection(bool $flat = false): Collection&MockObject
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('isEnabledFlat')->willReturn($flat);
        $collection->method('getStoreId')->willReturn(1);
        $collection->method('getItems')->willReturn([1, 2, 3]);
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
        $collection->method('addMediaGalleryData')->willReturnCallback(function () use ($collection) {
            $this->calls[] = 'gallery';

            return $collection;
        });

        return $collection;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }

    private function action(string $fullActionName, int $id): AbstractAction
    {
        $action = $this->createMock(AbstractAction::class);
        $action->method('getRequest')->willReturn($this->request($fullActionName, $id));

        return $action;
    }

    private function request(string $fullActionName, int $id): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($fullActionName);
        $request->method('getParam')->with('id')->willReturn((string) $id);

        return $request;
    }
}
