<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\DocumentWriter;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DocumentWriterTest extends TestCase
{
    public function testOnlyGroupsWhoseFingerprintMovedCountAsChanged(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('p_1', '5', ['_fp' => ['listing' => 'a', 'internal' => 'x'], 'category_ids' => [12]], 1);

        $changes = (new DocumentWriter($store, new NullLogger()))->replace('p_1', 'p_1', new BuildBatch(2, [
            new Document('5', 2, ['_fp' => ['listing' => 'a', 'internal' => 'y'], 'category_ids' => [12, 30]]),
            new Document('6', 2, ['_fp' => ['listing' => 'b']]),
        ]));

        $byId = [];

        foreach ($changes->changes() as $change) {
            $byId[$change->getId()] = $change;
        }

        $this->assertSame(['internal'], $byId[5]->getChangedGroups());
        $this->assertSame([30], $byId[5]->movedCategories());
        $this->assertTrue($byId[6]->isCreated());
        $this->assertSame(2, $changes->written());
    }

    /**
     * An older build losing the race changes nothing, so nothing is purged for it.
     */
    public function testAStaleWriteIsNotAChange(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('p_1', '5', ['_fp' => ['listing' => 'new']], 10);

        $changes = (new DocumentWriter($store, new NullLogger()))->replace('p_1', 'p_1', new BuildBatch(9, [
            new Document('5', 9, ['_fp' => ['listing' => 'old']]),
        ]));

        $this->assertSame([], $changes->changes());
        $this->assertSame(1, $changes->stale());
        $this->assertSame('new', $store->indexes['p_1']['5']->getFingerprint('listing'));
    }

    public function testRemovedDocumentsAreDeletedWithTheirCategories(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('p_1', '7', ['_fp' => [], 'category_ids' => [3]], 1);

        $changes = (new DocumentWriter($store, new NullLogger()))->replace('p_1', 'p_1', new BuildBatch(2, [], [7, 8]));

        $this->assertArrayNotHasKey('7', $store->indexes['p_1']);
        $this->assertTrue($changes->changes()[0]->isDeleted());
        $this->assertSame([3], $changes->changes()[0]->getCategoriesBefore());
    }

    /**
     * A rebuild writes into an empty new index, so there is nothing to delete there.
     */
    public function testWritingIntoAnotherIndexComparesButNeverDeletes(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('live', '7', ['_fp' => []], 1);

        (new DocumentWriter($store, new NullLogger()))->replace('build', 'live', new BuildBatch(2, [], [7]));

        $this->assertSame(0, $store->calls('delete'));
        $this->assertArrayHasKey('7', $store->indexes['live']);
    }
}
