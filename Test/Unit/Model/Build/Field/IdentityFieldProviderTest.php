<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\IdentityFieldProvider;

class IdentityFieldProviderTest extends FieldProviderTestCase
{
    public function testAnEnabledProductCarriesItsIdentity(): void
    {
        $draft = $this->runProvider(new IdentityFieldProvider(10), [$this->product([
            'entity_id' => 5, 'sku' => 'SKU-5', 'type_id' => 'simple', 'status' => 1, 'visibility' => 4,
        ])])[5];

        $this->assertSame('SKU-5', $draft->get('sku'));
        $this->assertSame(4, $draft->get('visibility'));
        $this->assertSame(10, (new IdentityFieldProvider(10))->getSortOrder());
    }

    public function testADisabledProductIsExcluded(): void
    {
        $draft = $this->runProvider(
            new IdentityFieldProvider(),
            [$this->product(['entity_id' => 5, 'status' => 2])]
        )[5];

        $this->assertSame('disabled', $draft->exclusionReason());
    }
}
