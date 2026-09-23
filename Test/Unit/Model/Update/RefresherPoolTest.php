<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use PHPUnit\Framework\TestCase;

class RefresherPoolTest extends TestCase
{
    public function testARefresherIsFoundByItsFamily(): void
    {
        $stock = $this->createMock(RefresherInterface::class);
        $stock->method('family')->willReturn(IndexFamily::Stock);

        $this->assertSame($stock, (new RefresherPool(['stock' => $stock]))->get(IndexFamily::Stock));
    }

    /**
     * A refresher registered under the wrong name would write one family's documents into another's index.
     */
    public function testARefresherUnderTheWrongNameIsRefused(): void
    {
        $stock = $this->createMock(RefresherInterface::class);
        $stock->method('family')->willReturn(IndexFamily::Stock);

        $this->expectException(InvalidArgumentException::class);

        (new RefresherPool(['price' => $stock]))->get(IndexFamily::Price);
    }
}
