<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Cache;

use Kingletas\CatalogIndex\Model\Cache\ParkedPurges;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class ParkedPurgesTest extends TestCase
{
    use StubbedDatabase;

    private const string TABLE = 'kingletas_catalog_index_parked_purge';

    public function testEachParkedTagIsItsOwnRow(): void
    {
        (new ParkedPurges($this->resourceConnection()))->park(['cat_p_1', 'cat_p_2']);

        [$method, $args] = $this->writes[0];
        $this->assertSame('insertMultiple', $method);
        $this->assertSame(self::TABLE, $args[0]);
        $this->assertSame([['tag' => 'cat_p_1'], ['tag' => 'cat_p_2']], $args[1]);
    }

    public function testParkingNothingWritesNothing(): void
    {
        (new ParkedPurges($this->resourceConnection()))->park([]);

        $this->assertSame([], $this->writes);
    }

    public function testTheOldestRowsComeBackDeduplicatedWithTheHighestIdRead(): void
    {
        $this->answers[self::TABLE] = [4 => 'cat_p_1', 5 => 'cat_p_2', 9 => 'cat_p_1'];

        [$upToId, $tags] = (new ParkedPurges($this->resourceConnection()))->oldest(3);

        $this->assertSame(9, $upToId);
        $this->assertSame(['cat_p_1', 'cat_p_2'], $tags);
        $calls = array_column($this->queriesOn(self::TABLE)[0]['calls'], 1, 0);
        $this->assertSame('purge_id ASC', $calls['order'][0]);
        $this->assertSame(3, $calls['limit'][0]);
    }

    public function testNothingParkedReadsAsNothingToRelease(): void
    {
        $this->assertSame([0, []], (new ParkedPurges($this->resourceConnection()))->oldest(10));
    }

    /**
     * Rows parked after the read have higher ids, so releasing by id never deletes a tag nobody purged.
     */
    public function testReleaseDeletesOnlyUpToTheIdThatWasRead(): void
    {
        $storage = new ParkedPurges($this->resourceConnection());

        $storage->release(9);
        $storage->release(0);

        $this->assertCount(1, $this->writes);
        $this->assertSame(['delete', [self::TABLE, ['purge_id <= ?' => 9]]], $this->writes[0]);
    }

    public function testCountingParkedTags(): void
    {
        $this->answers[self::TABLE] = '12';

        $this->assertSame(12, (new ParkedPurges($this->resourceConnection()))->count());
    }
}
