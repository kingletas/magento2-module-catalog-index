<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class AffectedProductResolverTest extends TestCase
{
    use StubbedDatabase;

    /**
     * A parent's document embeds its variants, so a change to one variant has to rebuild the parent too.
     */
    public function testChangedChildrenBringTheirParents(): void
    {
        $this->answers['catalog_product_relation'] = ['5'];

        $this->assertSame([5, 51], $this->resolver()->withParents([51, '51', 0]));
        $this->assertSame([], $this->resolver()->withParents([]));
    }

    public function testChildrenAndCategoriesAreGroupedByProduct(): void
    {
        $this->answers['catalog_product_relation'] = [
            ['parent_id' => '5', 'child_id' => '51'],
            ['parent_id' => '5', 'child_id' => '52'],
        ];
        $this->answers['catalog_category_product'] = [['product_id' => '5', 'category_id' => '12']];

        $this->assertSame([5 => [51, 52]], $this->resolver()->childrenOf([5]));
        $this->assertSame([5 => [12]], $this->resolver()->categoriesOf([5]));
    }

    private function resolver(): AffectedProductResolver
    {
        $link = $this->createMock(LinkField::class);
        $link->method('product')->willReturn('row_id');

        return new AffectedProductResolver($this->resourceConnection(), $link);
    }
}
