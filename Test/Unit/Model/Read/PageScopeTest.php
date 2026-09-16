<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use PHPUnit\Framework\TestCase;
use stdClass;

class PageScopeTest extends TestCase
{
    /**
     * A collection reused by a second block keeps the page that created it.
     */
    public function testTheFirstMarkWins(): void
    {
        $scope = new PageScope();
        $collection = new stdClass();

        $scope->mark($collection, PageType::CategoryListing);
        $scope->mark($collection, PageType::Widget);

        $this->assertSame(PageType::CategoryListing, $scope->pageOf($collection));
        $this->assertNull($scope->pageOf(new stdClass()));
    }

    public function testSkippedCodesAreHandedBackOnce(): void
    {
        $scope = new PageScope();
        $collection = new stdClass();

        $scope->skip($collection, ['name', 'media_gallery']);

        $this->assertSame(['name', 'media_gallery'], $scope->takeSkipped($collection));
        $this->assertNull($scope->takeSkipped($collection));
        $this->assertNull($scope->takeSkipped(new stdClass()));
    }

    public function testEachPageTargetsOnlyTheEntityItEnteredWith(): void
    {
        $scope = new PageScope();

        $scope->enter(PageType::ProductView, 5);
        $scope->enter(PageType::CategoryView, 12);
        $scope->enter(PageType::Widget, 0);

        $this->assertTrue($scope->targets(PageType::ProductView, 5));
        $this->assertFalse($scope->targets(PageType::ProductView, 12));
        $this->assertTrue($scope->targets(PageType::CategoryView, 12));
        $this->assertFalse($scope->targets(PageType::Widget, 0));

        $scope->leave(PageType::ProductView);

        $this->assertFalse($scope->targets(PageType::ProductView, 5));
        $this->assertTrue($scope->targets(PageType::CategoryView, 12));
    }
}
