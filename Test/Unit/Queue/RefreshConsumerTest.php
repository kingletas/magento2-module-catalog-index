<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Queue;

use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\CatalogIndex\Queue\RefreshConsumer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RefreshConsumerTest extends TestCase
{
    /** @var int[][] */
    private array $refreshed = [];

    /** @var IndexFamily[] */
    private array $rebuilt = [];

    /** @var string[] */
    private array $logged = [];

    public function testAMessageRefreshesItsIds(): void
    {
        $this->consumer()->process('{"family":"stock","ids":[3,4],"reason":"order"}');

        $this->assertSame([[3, 4]], $this->refreshed);
    }

    public function testAFullMessageRunsARebuild(): void
    {
        $this->consumer()->process('{"family":"stock","ids":[],"reason":"full"}');

        $this->assertSame([IndexFamily::Stock], $this->rebuilt);
    }

    /**
     * A message that can never succeed would be redelivered forever and block everything queued behind it.
     */
    public function testBadMessagesAndFailedRefreshesAreLoggedNotRethrown(): void
    {
        $consumer = $this->consumer(failing: true);

        $consumer->process('not json');
        $consumer->process('{"family":"basket","ids":[1]}');
        $consumer->process('{"family":"stock","ids":[1],"reason":"order"}');

        $this->assertSame(['warning', 'warning', 'error'], $this->logged);
    }

    private function consumer(bool $failing = false): RefreshConsumer
    {
        $refresher = $this->createMock(RefresherInterface::class);
        $refresher->method('family')->willReturn(IndexFamily::Stock);
        $refresher->method('refresh')->willReturnCallback(function (array $ids) use ($failing): void {
            if ($failing) {
                throw new \RuntimeException('store down');
            }

            $this->refreshed[] = $ids;
        });
        $rebuild = $this->createMock(FullRebuild::class);
        $rebuild->method('run')->willReturnCallback(function (IndexFamily $family): array {
            $this->rebuilt[] = $family;

            return [];
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (): void {
            $this->logged[] = 'warning';
        });
        $logger->method('error')->willReturnCallback(function (): void {
            $this->logged[] = 'error';
        });

        return new RefreshConsumer(new RefresherPool(['stock' => $refresher]), $rebuild, new Json(), $logger);
    }
}
