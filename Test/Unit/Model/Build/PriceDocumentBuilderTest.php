<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\PriceDocumentBuilder;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class PriceDocumentBuilderTest extends TestCase
{
    use StubbedDatabase;

    public function testEveryCustomerGroupsPricesSitInOneDocumentPerProduct(): void
    {
        $this->answers['catalog_product_index_price'] = [
            [
                'entity_id' => '5',
                'customer_group_id' => '0',
                'price' => '20',
                'final_price' => '18',
                'min_price' => '18',
                'max_price' => '18',
                'tier_price' => null,
            ],
            [
                'entity_id' => '5',
                'customer_group_id' => '1',
                'price' => '20',
                'final_price' => '16',
                'min_price' => '16',
                'max_price' => '16',
                'tier_price' => '15',
            ],
        ];

        $batch = (
            new PriceDocumentBuilder($this->resourceConnection(), new FingerprintCalculator())
        )->build([5, 6], 2, 99);

        $groups = $batch->documents[0]->get('groups');
        $this->assertSame(18.0, $groups['0']['final_price']);
        $this->assertSame(15.0, $groups['1']['tier_price']);
        $this->assertNull($groups['0']['tier_price']);
        $this->assertSame([6], $batch->removedIds);
        $this->assertContains(2, $this->whereValues('website_id = ?'));
    }
}
