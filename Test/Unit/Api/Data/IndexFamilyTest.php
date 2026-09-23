<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Api\Data;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use PHPUnit\Framework\TestCase;

class IndexFamilyTest extends TestCase
{
    public function testPriceAndStockAreWebsiteScopedAndRideThePriorityLane(): void
    {
        $this->assertTrue(IndexFamily::Price->isWebsiteScoped());
        $this->assertTrue(IndexFamily::Stock->isPriority());
        $this->assertFalse(IndexFamily::Product->isWebsiteScoped());
        $this->assertFalse(IndexFamily::Category->isPriority());
    }
}
