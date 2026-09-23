<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Stock;

use Kingletas\CatalogIndex\Model\Stock\StockLevel;
use PHPUnit\Framework\TestCase;

class StockLevelTest extends TestCase
{
    public function testTheLevelIsReadBackThroughTheGetters(): void
    {
        $level = new StockLevel(51, 7.0, 5.0, true, 2);

        $this->assertSame(51, $level->getProductId());
        $this->assertSame(7.0, $level->getQuantity());
        $this->assertSame(5.0, $level->getSalableQuantity());
        $this->assertTrue($level->isSalable());
        $this->assertSame(2, $level->getStockId());
    }
}
