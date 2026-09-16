<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Index;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Which store views and websites have documents.
 */
class ScopeResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return int[]
     */
    public function scopeIds(IndexFamily $family): array
    {
        return $family->isWebsiteScoped() ? $this->websiteIds() : $this->storeIds();
    }

    /**
     * @return int[] Active store views, admin excluded.
     */
    public function storeIds(): array
    {
        $ids = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() > 0 && (bool) $store->getIsActive()) {
                $ids[] = (int) $store->getId();
            }
        }

        sort($ids);

        return $ids;
    }

    /**
     * @return int[] Websites with at least one active store view.
     */
    public function websiteIds(): array
    {
        $ids = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() > 0 && (bool) $store->getIsActive()) {
                $ids[(int) $store->getWebsiteId()] = (int) $store->getWebsiteId();
            }
        }

        ksort($ids);

        return array_values($ids);
    }

    public function websiteIdOf(int $storeId): int
    {
        return (int) $this->storeManager->getStore($storeId)->getWebsiteId();
    }

    public function rootCategoryOf(int $storeId): int
    {
        $groupId = (int) $this->storeManager->getStore($storeId)->getStoreGroupId();

        return (int) $this->storeManager->getGroup((string) $groupId)->getRootCategoryId();
    }

    /**
     * @return int[] Active store views of one website.
     */
    public function storeIdsOfWebsite(int $websiteId): array
    {
        return array_values(array_filter(
            $this->storeIds(),
            fn (int $storeId): bool => $this->websiteIdOf($storeId) === $websiteId
        ));
    }
}
