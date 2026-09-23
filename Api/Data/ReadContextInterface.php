<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * Who a page is being built for.
 *
 * @api
 */
interface ReadContextInterface
{
    public function getPage(): PageType;

    public function getStoreId(): int;

    public function getWebsiteId(): int;

    public function getCustomerGroupId(): int;
}
