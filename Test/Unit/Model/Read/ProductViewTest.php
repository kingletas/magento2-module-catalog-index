<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use PHPUnit\Framework\TestCase;

class ProductViewTest extends TestCase
{
    public function testTheDocumentIsReadBackAsAttributesAndPageData(): void
    {
        $view = new ProductView(5, [
            'entity_id' => 5,
            'sku' => 'SKU-5',
            'type_id' => 'configurable',
            'listing_attributes' => ['name' => 'Trail Jacket'],
            'detail_attributes' => ['description' => 'Warm.'],
            'request_path' => 'trail-jacket.html',
            'media_gallery' => ['images' => []],
            'tier_price' => [['price' => 8.0]],
            'rating_summary' => 80,
            'reviews_count' => 2,
            'variants' => [['entity_id' => 51], 'junk'],
            'configurable_options' => ['93' => [['value_index' => '49']], '141' => 'junk'],
            'category_ids' => ['12'],
            'has_custom_options' => false,
        ], ['final_price' => 18.0], ['is_salable' => true, 'children' => ['51' => true, '52' => false]]);

        $this->assertSame(
            [
                'description' => 'Warm.',
                'name' => 'Trail Jacket',
                'entity_id' => 5,
                'sku' => 'SKU-5',
                'type_id' => 'configurable',
            ],
            $view->attributes()
        );
        $this->assertSame(5, $view->getId());
        $this->assertSame('trail-jacket.html', $view->requestPath());
        $this->assertSame([['price' => 8.0]], $view->tierPrice());
        $this->assertSame(['rating_summary' => 80, 'reviews_count' => 2], $view->reviewSummary());
        $this->assertSame(['93' => [['value_index' => '49']]], $view->getConfigurable()->options());
        $this->assertSame([], (new ProductView(5, []))->getConfigurable()->options());
        $this->assertSame([12], $view->categoryIds());
        $this->assertNull((new ProductView(5, []))->categoryIds());
        $this->assertSame(['final_price' => 18.0], $view->price());
        $this->assertTrue($view->isSalable());
        $this->assertTrue($view->isDetailServable());
    }

    /**
     * A page built without stock, options or a supported type would render something the database would not.
     */
    public function testAProductIsOnlyServableWhenNothingItShowsLivesOutsideTheDocument(): void
    {
        $this->assertFalse(
            (new ProductView(5, ['type_id' => 'bundle', 'has_custom_options' => false], null, []))->isDetailServable()
        );
        $this->assertFalse(
            (new ProductView(5, ['type_id' => 'simple', 'has_custom_options' => true], null, []))->isDetailServable()
        );
        $this->assertFalse((new ProductView(5, ['type_id' => 'simple'], null, []))->isDetailServable());
        $this->assertFalse(
            (new ProductView(5, ['type_id' => 'simple', 'has_custom_options' => false], null, null))->isDetailServable()
        );
        $this->assertNull((new ProductView(5, []))->isSalable());
        $this->assertNull((new ProductView(5, []))->reviewSummary());
    }

    public function testACategoryViewReadsItsAttributesAndPath(): void
    {
        $view = new CategoryView(12, ['attributes' => ['name' => 'Jackets'], 'request_path' => '']);

        $this->assertSame(['name' => 'Jackets'], $view->attributes());
        $this->assertNull($view->requestPath());
    }
}
