<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Index;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ScopeResolverTest extends TestCase
{
    public function testOnlyActiveStoreViewsAndTheirWebsitesHaveDocuments(): void
    {
        $resolver = new ScopeResolver($this->storeManager());

        $this->assertSame([1, 3], $resolver->storeIds());
        $this->assertSame([1, 2], $resolver->websiteIds());
        $this->assertSame([1, 2], $resolver->scopeIds(IndexFamily::Price));
        $this->assertSame([1, 3], $resolver->scopeIds(IndexFamily::Product));
        $this->assertSame([3], $resolver->storeIdsOfWebsite(2));
    }

    public function testTheRootCategoryComesFromTheStoreGroup(): void
    {
        $this->assertSame(40, (new ScopeResolver($this->storeManager()))->rootCategoryOf(3));
    }

    private function storeManager(): StoreManagerInterface
    {
        $stores = [
            0 => $this->store(0, 0, true, 0),
            1 => $this->store(1, 1, true, 1),
            2 => $this->store(2, 1, false, 1),
            3 => $this->store(3, 2, true, 2),
        ];
        $group = $this->createMock(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(40);
        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->method('getStores')->willReturn($stores);
        $manager->method('getStore')->willReturnCallback(static fn (int $id): Store => $stores[$id]);
        $manager->method('getGroup')->willReturn($group);

        return $manager;
    }

    private function store(int $id, int $websiteId, bool $active, int $groupId): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getWebsiteId')->willReturn($websiteId);
        $store->method('getIsActive')->willReturn($active);
        $store->method('getStoreGroupId')->willReturn($groupId);

        return $store;
    }
}
