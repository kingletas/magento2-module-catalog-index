<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Plugin\Indexer;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Plugin\Indexer\FollowCoreFullReindex;
use PHPUnit\Framework\TestCase;

class FollowCoreFullReindexTest extends TestCase
{
    public function testAFullCoreReindexQueuesAFullRebuildOfTheFamilyItFeeds(): void
    {
        $publisher = $this->createMock(RefreshPublisher::class);
        $publisher->expects($this->once())->method('publishFull')->with(IndexFamily::Stock);

        $this->assertNull((new FollowCoreFullReindex($publisher, 'stock'))->afterExecuteFull(new \stdClass()));
    }
}
