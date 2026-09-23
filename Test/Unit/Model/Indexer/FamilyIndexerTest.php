<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Indexer;

use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Indexer\FamilyIndexer;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use PHPUnit\Framework\TestCase;

class FamilyIndexerTest extends TestCase
{
    public function testEveryEntryPointReachesTheFamilysRefresherOrRebuild(): void
    {
        $refreshed = [];
        $refresher = $this->createMock(RefresherInterface::class);
        $refresher->method('family')->willReturn(IndexFamily::Price);
        $refresher->method('refresh')->willReturnCallback(static function (array $ids) use (&$refreshed): void {
            $refreshed[] = $ids;
        });
        $rebuild = $this->createMock(FullRebuild::class);
        $rebuild->expects($this->once())->method('run')->with(IndexFamily::Price);
        $indexer = new FamilyIndexer(new RefresherPool(['price' => $refresher]), $rebuild, 'price');

        $indexer->execute(['4', 5]);
        $indexer->executeList([6]);
        $indexer->executeRow('7');
        $indexer->executeFull();

        $this->assertSame([[4, 5], [6], [7]], $refreshed);
    }
}
