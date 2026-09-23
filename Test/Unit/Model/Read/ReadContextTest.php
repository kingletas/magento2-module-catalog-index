<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use PHPUnit\Framework\TestCase;

class ReadContextTest extends TestCase
{
    public function testThePageAndShopperAreReadBackThroughTheGetters(): void
    {
        $context = new ReadContext(PageType::SearchListing, 3, 2, 4);

        $this->assertSame(PageType::SearchListing, $context->getPage());
        $this->assertSame(3, $context->getStoreId());
        $this->assertSame(2, $context->getWebsiteId());
        $this->assertSame(4, $context->getCustomerGroupId());
    }
}
