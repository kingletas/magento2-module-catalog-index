<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Config;

/**
 * Decides whether a page reads documents, from the settings and the circuit breaker.
 */
class ReadGate
{
    public function __construct(
        private readonly Config $config,
        private readonly CircuitBreaker $breaker
    ) {
    }

    public function decide(PageType $page, int $storeId): ReadDecision
    {
        if (!$this->config->isEnabled() || !$this->isPageEnabled($page, $storeId)) {
            return ReadDecision::Disabled;
        }

        return $this->breaker->isOpen() ? ReadDecision::BreakerOpen : ReadDecision::Allow;
    }

    private function isPageEnabled(PageType $page, int $storeId): bool
    {
        return match ($page) {
            PageType::CategoryListing => $this->config->isCategoryListingEnabled($storeId),
            PageType::SearchListing => $this->config->isSearchListingEnabled($storeId),
            PageType::ProductView => $this->config->isProductViewEnabled($storeId),
            PageType::CategoryView => $this->config->isCategoryViewEnabled($storeId),
            PageType::CategoryTree => $this->config->isCategoryTreeEnabled($storeId),
            PageType::LinkedProducts => $this->config->isLinkedProductsEnabled($storeId),
            PageType::Widget => $this->config->isWidgetEnabled($storeId),
            PageType::GraphQl => $this->config->isGraphQlEnabled($storeId),
        };
    }
}
