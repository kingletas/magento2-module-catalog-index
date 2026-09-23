<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;
use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use PHPUnit\Framework\TestCase;

class ChangeSetTest extends TestCase
{
    public function testTheSameDocumentInTwoScopesIsKeptTwiceAndInOneScopeOnce(): void
    {
        $set = new ChangeSet();
        $set->add(new Change(5, true), '1');
        $set->add(new Change(5, true), '2');
        $other = new ChangeSet();
        $other->add(new Change(5, false, true), '2');
        $set->merge($other);

        $changes = $set->changes();

        $this->assertCount(2, $changes);
        $this->assertTrue($changes[0]->isCreated());
        $this->assertTrue($changes[1]->isDeleted());
    }

    public function testAnotherImplementationIsMergedWithoutLosingAChange(): void
    {
        $set = new ChangeSet();
        $set->add(new Change(5, true), '1');
        $other = $this->createMock(ChangeSetInterface::class);
        $other->method('changes')->willReturn([new Change(5, false, true)]);
        $other->method('written')->willReturn(1);
        $other->method('stale')->willReturn(2);
        $other->method('failed')->willReturn(['6' => 'refused']);

        $set->merge($other);

        $this->assertCount(2, $set->changes());
        $this->assertSame(1, $set->written());
        $this->assertSame(2, $set->stale());
        $this->assertSame(['6' => 'refused'], $set->failed());
    }
}
