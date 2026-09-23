<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Observer;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Observer\FlushReadMetrics;
use Kingletas\CatalogIndex\Observer\RefreshOrderedProducts;
use Kingletas\CatalogIndex\Observer\RefreshSavedCategory;
use Kingletas\CatalogIndex\Observer\RefreshSavedProduct;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ObserversTest extends TestCase
{
    /** @var array<int, array{0: string, 1: int[], 2: string}> */
    private array $published = [];

    public function testAnOrderOrRefundRefreshesTheStockOfEveryItem(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getAllItems')->willReturn(
            [new DataObject(['product_id' => 5]), new DataObject(['product_id' => 51])]
        );
        $refund = $this->createMock(Creditmemo::class);
        $refund->method('getAllItems')->willReturn([new DataObject(['product_id' => 7])]);
        $observer = new RefreshOrderedProducts($this->publisher());

        $observer->execute($this->observer(['order' => $order]));
        $observer->execute($this->observer(['creditmemo' => $refund]));
        $observer->execute($this->observer(['order' => new DataObject()]));

        $this->assertSame([['stock', [5, 51], 'order'], ['stock', [7], 'order']], $this->published);
    }

    public function testASavedProductRefreshesItsDocumentAndStockAndASavedCategoryItsOwn(): void
    {
        (new RefreshSavedProduct($this->publisher()))->execute(
            $this->observer(['product' => new DataObject(['entity_id' => 5])])
        );
        (new RefreshSavedProduct($this->publisher()))->execute($this->observer(['product' => new DataObject()]));
        (new RefreshSavedCategory($this->publisher()))->execute(
            $this->observer(['category' => new DataObject(['entity_id' => 12])])
        );

        $this->assertSame(
            [['product', [5], 'product_saved'], ['stock', [5], 'product_saved'], ['category', [12], 'category_saved']],
            $this->published
        );
    }

    /**
     * The counters are written as the response goes out, and a broken cache must not break the page.
     */
    public function testReadCountersAreFlushedAndAFailureIsSwallowed(): void
    {
        $recorder = $this->createMock(FallbackRecorder::class);
        $recorder->expects($this->once())->method('flush')->willThrowException(new \RuntimeException('cache down'));

        (new FlushReadMetrics($recorder, new NullLogger()))->execute($this->observer([]));
    }

    private function publisher(): RefreshPublisher
    {
        $publisher = $this->createMock(RefreshPublisher::class);
        $publisher->method('publish')->willReturnCallback(function (
            IndexFamily $family,
            array $ids,
            string $reason
        ): void {
            $this->published[] = [$family->value, $ids, $reason];
        });

        return $publisher;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }
}
