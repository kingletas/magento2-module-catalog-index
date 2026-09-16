<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\CategoryFieldProvider;

class CategoryFieldProviderTest extends FieldProviderTestCase
{
    public function testCategoriesAndPositionsAreLoadedOnceForTheBatch(): void
    {
        $this->answers['catalog_category_product'] = [
            ['product_id' => '5', 'category_id' => '30', 'position' => '2'],
            ['product_id' => '5', 'category_id' => '12', 'position' => '0'],
        ];

        $drafts = $this->runProvider(new CategoryFieldProvider($this->resourceConnection()), [
            $this->product(['entity_id' => 5]),
            $this->product(['entity_id' => 6]),
        ]);

        $this->assertCount(1, $this->queries);
        $this->assertSame([12, 30], $drafts[5]->get('category_ids'));
        $this->assertSame([12 => 0, 30 => 2], $drafts[5]->get('category_positions'));
        $this->assertSame([], $drafts[6]->get('category_ids'));
    }
}
