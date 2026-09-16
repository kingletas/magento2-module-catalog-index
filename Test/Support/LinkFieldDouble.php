<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Model\Build\LinkField;

/**
 * A link field that reports the column a store without content staging uses, or any column a test names.
 */
trait LinkFieldDouble
{
    protected function linkField(string $field = 'entity_id'): LinkField
    {
        $link = $this->createMock(LinkField::class);
        $link->method('product')->willReturn($field);
        $link->method('category')->willReturn($field);
        $link->method('isStaged')->willReturn($field !== 'entity_id');

        return $link;
    }
}
