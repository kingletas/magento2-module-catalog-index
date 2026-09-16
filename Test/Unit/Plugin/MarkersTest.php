<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Plugin;

use Kingletas\CatalogIndex\Model\GraphQl\MarkDocumentCollection;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\CatalogIndex\Plugin\Layer\MarkSearchListing;
use Kingletas\CatalogIndex\Plugin\Link\MarkLinkedCollection;
use Kingletas\CatalogIndex\Plugin\Widget\MarkWidgetCollection;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\CollectionFilterInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection as LinkCollection;
use Magento\Framework\Api\SearchCriteriaInterface;
use PHPUnit\Framework\TestCase;

class MarkersTest extends TestCase
{
    public function testEachStorefrontSurfaceMarksItsCollectionWithItsOwnPage(): void
    {
        $scope = new PageScope();
        $listing = $this->createMock(Collection::class);
        $linked = $this->createMock(LinkCollection::class);
        $widget = $this->createMock(Collection::class);
        $graphql = $this->createMock(Collection::class);

        $arguments = (new MarkSearchListing($scope))->beforeFilter(
            $this->createMock(CollectionFilterInterface::class),
            $listing,
            $this->createMock(Category::class)
        );
        $this->assertSame('x', (new MarkLinkedCollection($scope))->afterSetPositionOrder($linked, 'x'));
        $this->assertSame($widget, (new MarkWidgetCollection($scope))->afterCreateCollection(new \stdClass(), $widget));
        $this->assertSame($graphql, (new MarkDocumentCollection($scope))->process(
            $graphql,
            $this->createMock(SearchCriteriaInterface::class),
            ['name']
        ));

        $this->assertNull($arguments);
        $this->assertSame(PageType::SearchListing, $scope->pageOf($listing));
        $this->assertSame(PageType::LinkedProducts, $scope->pageOf($linked));
        $this->assertSame(PageType::Widget, $scope->pageOf($widget));
        $this->assertSame(PageType::GraphQl, $scope->pageOf($graphql));
    }

    public function testAWidgetReturningSomethingElseIsLeftAlone(): void
    {
        $scope = new PageScope();

        $this->assertSame('html', (new MarkWidgetCollection($scope))->afterCreateCollection(new \stdClass(), 'html'));
    }
}
