<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use PHPUnit\Framework\TestCase;

class RefreshRequestTest extends TestCase
{
    public function testIdsAreCleanedAndTheReasonDecidesTheLane(): void
    {
        $order = new RefreshRequest(IndexFamily::Stock, ['9', 3, 3, 0, -2], RefreshRequest::REASON_ORDER);
        $save = new RefreshRequest(IndexFamily::Product, [1], RefreshRequest::REASON_PRODUCT_SAVED);

        $this->assertSame([3, 9], $order->ids);
        $this->assertFalse($order->mayRunInline());
        $this->assertFalse($order->isInChangelog());
        $this->assertTrue($save->mayRunInline());
        $this->assertTrue($save->isInChangelog());
        $this->assertSame(['family' => 'stock', 'ids' => [3, 9], 'reason' => 'order'], $order->toArray());
    }

    public function testOnlyAFullRebuildMayNameNoIds(): void
    {
        $this->assertTrue((new RefreshRequest(IndexFamily::Price, [], RefreshRequest::REASON_FULL))->isFull());

        $this->expectException(InvalidArgumentException::class);

        new RefreshRequest(IndexFamily::Price, [0], RefreshRequest::REASON_MANUAL);
    }
}
