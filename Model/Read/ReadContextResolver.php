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
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolves the store, website and customer group a storefront request is for.
 */
class ReadContextResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpContext $httpContext
    ) {
    }

    public function resolve(PageType $page, ?int $storeId = null): ReadContextInterface
    {
        $store = $storeId === null || $storeId === 0
            ? $this->storeManager->getStore()
            : $this->storeManager->getStore($storeId);

        return new ReadContext(
            $page,
            (int) $store->getId(),
            (int) $store->getWebsiteId(),
            (int) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP)
        );
    }
}
