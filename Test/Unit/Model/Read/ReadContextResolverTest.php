<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class ReadContextResolverTest extends TestCase
{
    public function testTheShoppersGroupAndTheStoresWebsiteAreResolved(): void
    {
        $current = $this->store(1, 1);
        $other = $this->store(4, 2);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturnCallback(
            static fn (?int $id = null): Store => $id === 4 ? $other : $current
        );
        $http = $this->createMock(HttpContext::class);
        $http->method('getValue')->willReturn('3');
        $resolver = new ReadContextResolver($stores, $http);

        $context = $resolver->resolve(PageType::SearchListing, 4);

        $this->assertSame(
            [4, 2, 3],
            [$context->getStoreId(), $context->getWebsiteId(), $context->getCustomerGroupId()]
        );
        $this->assertSame(1, $resolver->resolve(PageType::SearchListing, 0)->getStoreId());
        $this->assertSame(PageType::SearchListing, $context->getPage());
    }

    private function store(int $id, int $websiteId): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getWebsiteId')->willReturn($websiteId);

        return $store;
    }
}
