<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Schedule;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class ScheduleStorageTest extends TestCase
{
    use StubbedDatabase;

    public function testMomentsAreRecordedOneRowEachAndDuplicatesCollapse(): void
    {
        (new ScheduleStorage($this->resourceConnection()))->record(IndexFamily::Product, 2, [
            5 => [new DateTimeImmutable('@100'), new DateTimeImmutable('@200')],
        ]);

        [$method, $args] = $this->writes[0];
        $this->assertSame('insertOnDuplicate', $method);
        $this->assertSame(['family' => 'product', 'entity_id' => 5, 'scope_id' => 2, 'due_at' => 200], $args[1][1]);
    }

    public function testNothingToRecordWritesNothing(): void
    {
        (new ScheduleStorage($this->resourceConnection()))->record(IndexFamily::Product, 2, []);

        $this->assertSame([], $this->writes);
    }

    public function testDueRowsWithAnUnknownFamilyAreSkipped(): void
    {
        $this->answers['kingletas_catalog_index_schedule'] = [
            ['schedule_id' => '1', 'family' => 'product', 'entity_id' => '5', 'scope_id' => '1', 'due_at' => '90'],
            ['schedule_id' => '2', 'family' => 'wishlist', 'entity_id' => '6', 'scope_id' => '1', 'due_at' => '95'],
        ];

        $due = (new ScheduleStorage($this->resourceConnection()))->due(100, 10);

        $this->assertCount(1, $due);
        $this->assertSame(IndexFamily::Product, $due[0]->family);
        $this->assertContains(100, $this->whereValues('due_at <= ?'));
    }

    public function testRemovingAndCounting(): void
    {
        $this->answers['kingletas_catalog_index_schedule'] = '4';
        $storage = new ScheduleStorage($this->resourceConnection());

        $storage->remove([1, 2]);
        $storage->remove([]);

        $this->assertSame(4, $storage->pending());
        $this->assertCount(1, $this->writes);
    }
}
