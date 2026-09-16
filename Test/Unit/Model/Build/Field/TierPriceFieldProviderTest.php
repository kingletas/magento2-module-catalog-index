<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\TierPriceFieldProvider;

class TierPriceFieldProviderTest extends FieldProviderTestCase
{
    public function testTiersAreShapedLikeTheTierPriceBackendLoadsThem(): void
    {
        $this->answers['catalog_product_entity_tier_price'] = [
            [
                'value_id' => '1',
                'entity_id' => '5',
                'all_groups' => '1',
                'customer_group_id' => '0',
                'qty' => '10',
                'value' => '8.5',
                'percentage_value' => null,
                'website_id' => '0',
            ],
        ];

        $draft = $this->runProvider(new TierPriceFieldProvider($this->resourceConnection(), $this->linkField()), [
            $this->product(['entity_id' => 5]),
        ])[5];

        $this->assertSame(32000, $draft->get('tier_price')[0]['cust_group']);
        $this->assertSame(10.0, $draft->get('tier_price')[0]['price_qty']);
        $this->assertContains([0, 1], $this->whereValues('website_id IN (?)'));
    }
}
