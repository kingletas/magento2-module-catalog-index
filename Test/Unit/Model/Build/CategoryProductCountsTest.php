<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\CategoryProductCounts;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class CategoryProductCountsTest extends TestCase
{
    use StubbedDatabase;

    public function testABatchOfAnySizeCostsOneQuery(): void
    {
        $this->answers['catalog_category_product'] = [12 => '28', 18 => '4'];

        $counts = new CategoryProductCounts($this->resourceConnection());
        $counts->prepare([12, 18, 21]);

        $this->assertCount(1, $this->queriesOn('catalog_category_product'));
        $this->assertSame(28, $counts->productCount(12));
        $this->assertSame(4, $counts->productCount(18));
    }

    public function testACategoryWithNoProductsCountsZero(): void
    {
        $counts = new CategoryProductCounts($this->resourceConnection());
        $counts->prepare([12]);

        $this->assertSame(0, $counts->productCount(12));
    }

    public function testAnEmptyBatchAsksNothing(): void
    {
        $counts = new CategoryProductCounts($this->resourceConnection());
        $counts->prepare([]);

        $this->assertSame([], $this->queries);
    }

    public function testASecondBatchForgetsTheFirst(): void
    {
        $this->answers['catalog_category_product'] = [12 => '28'];
        $counts = new CategoryProductCounts($this->resourceConnection());
        $counts->prepare([12]);

        $this->answers['catalog_category_product'] = [18 => '4'];
        $counts->prepare([18]);

        $this->assertSame(0, $counts->productCount(12));
        $this->assertSame(4, $counts->productCount(18));
    }
}
