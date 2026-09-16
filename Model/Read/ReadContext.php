<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

/**
 * Who a page is being built for.
 */
class ReadContext
{
    public function __construct(
        public readonly PageType $page,
        public readonly int $storeId,
        public readonly int $websiteId,
        public readonly int $customerGroupId
    ) {
    }
}
