<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Api\Data\ReadContextInterface;

/**
 * Who a page is being built for.
 */
class ReadContext implements ReadContextInterface
{
    public function __construct(
        private readonly PageType $page,
        private readonly int $storeId,
        private readonly int $websiteId,
        private readonly int $customerGroupId
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getPage(): PageType
    {
        return $this->page;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getWebsiteId(): int
    {
        return $this->websiteId;
    }

    /**
     * @inheritDoc
     */
    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }
}
