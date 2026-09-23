<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Stock;

use Kingletas\CatalogIndex\Model\Stock\LegacyStockReader;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class LegacyStockReaderTest extends TestCase
{
    use StubbedDatabase;

    public function testTheSingleSourceStatusIsRead(): void
    {
        $this->answers['cataloginventory_stock_status'] = [
            ['product_id' => '5', 'qty' => '4.0000', 'stock_status' => '1', 'stock_id' => '1'],
        ];
        $reader = new LegacyStockReader($this->resourceConnection());

        $levels = $reader->read([5, 6], 3);

        $this->assertTrue($reader->isApplicable());
        $this->assertSame([5], array_keys($levels));
        $this->assertTrue($levels[5]->isSalable());
        $this->assertSame(4.0, $levels[5]->getQuantity());
        $this->assertSame([], $reader->read([], 3));
    }
}
