<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RefreshPublisherTest extends TestCase
{
    use ShippedConfig;

    /** @var array<int, array{0: string, 1: string}> */
    private array $published = [];

    /** @var int[][] */
    private array $inline = [];

    private bool $queueDown = false;

    public function testStockGoesToThePriorityTopicInBatches(): void
    {
        $this->publisher(['updates/batch_size' => '2'])->publish(
            IndexFamily::Stock,
            [1, 2, 3],
            RefreshRequest::REASON_ORDER
        );

        $this->assertSame(
            ['kingletas.catalog_index.priority', 'kingletas.catalog_index.priority'],
            array_column($this->published, 0)
        );
        $this->assertSame('{"family":"stock","ids":[3],"reason":"order"}', $this->published[1][1]);
    }

    public function testProductsGoToTheRefreshTopic(): void
    {
        $this->publisher()->publish(IndexFamily::Product, [1], RefreshRequest::REASON_PRODUCT_SAVED);

        $this->assertSame('kingletas.catalog_index.refresh', $this->published[0][0]);
    }

    /**
     * Inline mode must never make a shopper wait for a stock refresh during checkout.
     */
    public function testInlineModeRefreshesSmallBatchesButNeverAnOrder(): void
    {
        $publisher = $this->publisher(['updates/mode' => 'inline', 'updates/inline_limit' => '2']);

        $publisher->publish(IndexFamily::Product, [1, 2], RefreshRequest::REASON_PRODUCT_SAVED);
        $publisher->publish(IndexFamily::Product, [1, 2, 3], RefreshRequest::REASON_PRODUCT_SAVED);
        $publisher->publish(IndexFamily::Stock, [1], RefreshRequest::REASON_ORDER);

        $this->assertSame([[1, 2]], $this->inline);
        $this->assertCount(2, $this->published);
    }

    public function testScheduleModeLeavesSavesToTheChangeLogButStillSendsOrders(): void
    {
        $publisher = $this->publisher(['updates/mode' => 'schedule']);

        $publisher->publish(IndexFamily::Product, [1], RefreshRequest::REASON_PRODUCT_SAVED);
        $publisher->publish(IndexFamily::Stock, [1], RefreshRequest::REASON_ORDER);

        $this->assertSame(['{"family":"stock","ids":[1],"reason":"order"}'], array_column($this->published, 1));
    }

    public function testAFullRebuildIsOneMessageWithNoIds(): void
    {
        $this->publisher()->publishFull(IndexFamily::Price);

        $this->assertSame('{"family":"price","ids":[],"reason":"full"}', $this->published[0][1]);
    }

    /**
     * Publishing runs inside checkout, so a broken queue is logged and never thrown at the shopper.
     */
    public function testAQueueFailureNeverEscapes(): void
    {
        $this->queueDown = true;

        $this->publisher()->publish(IndexFamily::Stock, [1], RefreshRequest::REASON_ORDER);

        $this->assertSame([], $this->published);
    }

    public function testNothingIsSentWhileTheModuleIsDisabled(): void
    {
        $this->publisher(['general/enabled' => '0'])->publish(IndexFamily::Stock, [1], RefreshRequest::REASON_ORDER);

        $this->assertSame([], $this->published);
    }

    /**
     * @param array<string, string> $config
     */
    private function publisher(array $config = []): RefreshPublisher
    {
        $queue = $this->createMock(PublisherInterface::class);
        $queue->method('publish')->willReturnCallback(function (string $topic, string $message): void {
            if ($this->queueDown) {
                throw new \RuntimeException('connection refused');
            }

            $this->published[] = [$topic, $message];
        });
        $refresher = $this->createMock(RefresherInterface::class);
        $refresher->method('family')->willReturn(IndexFamily::Product);
        $refresher->method('refresh')->willReturnCallback(function (array $ids): void {
            $this->inline[] = $ids;
        });

        return new RefreshPublisher(
            $this->config($config),
            $queue,
            new RefresherPool(['product' => $refresher]),
            new Json(),
            new NullLogger()
        );
    }
}
