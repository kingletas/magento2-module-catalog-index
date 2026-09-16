<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use PHPUnit\Framework\TestCase;

class ChangeTest extends TestCase
{
    public function testMovedCategoriesAreTheOnesJoinedOrLeft(): void
    {
        $change = new Change(5, false, false, [], [1, 2, 3], [2, 3, 4]);

        $this->assertSame([1, 4], $change->movedCategories());
        $this->assertSame([1, 2, 3, 4], $change->allCategories());
        $this->assertFalse($change->touchesAny(['listing']));
        $this->assertFalse($change->isNothing());
    }

    public function testASetIgnoresNonChangesAndAddsUpOutcomes(): void
    {
        $set = new ChangeSet();
        $set->add(new Change(1));
        $set->add(new Change(2, true), '1');
        $set->record(2, 1, []);
        $other = new ChangeSet();
        $other->add(new Change(2, false, false, ['detail']), '2');
        $other->record(1, 0, ['3' => 'bad']);
        $set->merge($other);

        $this->assertCount(2, $set->changes());
        $this->assertSame(3, $set->written());
        $this->assertSame(1, $set->stale());
        $this->assertSame(['3' => 'bad'], $set->failed());
    }
}
