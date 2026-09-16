<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Stock;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\StockReaderInterface;
use Kingletas\CatalogIndex\Model\Stock\StockReaderPool;
use PHPUnit\Framework\TestCase;

class StockReaderPoolTest extends TestCase
{
    public function testTheFirstApplicableReaderIsChosenOnce(): void
    {
        $msi = $this->reader(false);
        $legacy = $this->reader(true);
        $pool = new StockReaderPool(['msi' => $msi, 'legacy' => $legacy]);

        $this->assertSame($legacy, $pool->reader());
        $this->assertSame($legacy, $pool->reader());
    }

    public function testNoApplicableReaderIsAnError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StockReaderPool(['msi' => $this->reader(false)]))->reader();
    }

    private function reader(bool $applicable): StockReaderInterface
    {
        $reader = $this->createMock(StockReaderInterface::class);
        $reader->method('isApplicable')->willReturn($applicable);

        return $reader;
    }
}
