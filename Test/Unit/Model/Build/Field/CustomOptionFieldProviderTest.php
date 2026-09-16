<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\CustomOptionFieldProvider;

class CustomOptionFieldProviderTest extends FieldProviderTestCase
{
    public function testAProductWithAnyOptionIsMarked(): void
    {
        $this->answers['catalog_product_option'] = ['900'];

        $drafts = $this->runProvider(new CustomOptionFieldProvider(
            $this->resourceConnection(),
            $this->linkField('row_id')
        ), [
            $this->product(['entity_id' => 5, 'row_id' => 900]),
            $this->product(['entity_id' => 6, 'row_id' => 901]),
        ]);

        $this->assertTrue($drafts[5]->get('has_custom_options'));
        $this->assertFalse($drafts[6]->get('has_custom_options'));
    }
}
