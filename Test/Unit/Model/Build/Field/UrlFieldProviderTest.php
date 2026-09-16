<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\UrlFieldProvider;

class UrlFieldProviderTest extends FieldProviderTestCase
{
    public function testTheProductsOwnRewriteForTheStoreIsUsed(): void
    {
        $this->answers['url_rewrite'] = [['entity_id' => '5', 'request_path' => 'trail-jacket.html']];

        $drafts = $this->runProvider(new UrlFieldProvider($this->resourceConnection()), [
            $this->product(['entity_id' => 5]),
            $this->product(['entity_id' => 6]),
        ]);

        $this->assertSame('trail-jacket.html', $drafts[5]->get('request_path'));
        $this->assertFalse($drafts[6]->has('request_path'));
        $this->assertContains(1, $this->whereValues('store_id = ?'));
        $this->assertContains('product', $this->whereValues('entity_type = ?'));
    }
}
