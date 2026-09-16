<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Cache;

use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use PHPUnit\Framework\TestCase;

class PurgePlannerTest extends TestCase
{
    public function testAVisibleProductChangePurgesTheProductAndOnlyCategoriesItMovedBetween(): void
    {
        $tags = (new PurgePlanner())->forProducts($this->set(
            new Change(5, false, false, ['listing'], [1, 2], [2, 3]),
            new Change(6, false, false, ['internal'], [4], [4]),
            new Change(7, true, false, [], [], [8])
        ));

        $this->assertSame(['cat_c_p_1', 'cat_c_p_3', 'cat_c_p_8', 'cat_p_5', 'cat_p_7'], $tags);
    }

    /**
     * Stock moving from 40 to 39 changes nothing a shopper sees, so it must not purge a single page.
     */
    public function testOnlyASalabilityFlipReachesCategoryPages(): void
    {
        $planner = new PurgePlanner();
        $set = $this->set(
            new Change(5, false, false, ['salable']),
            new Change(6, false, false, ['level']),
            new Change(7, false, false, ['internal'])
        );

        $this->assertSame(['cat_c_p_12', 'cat_p_5', 'cat_p_6'], $planner->forStock($set, [5 => [12], 6 => [13]]));
        $this->assertSame([5], $planner->salabilityFlips($set));
    }

    public function testPricesAndCategoriesPurgeTheirOwnTags(): void
    {
        $planner = new PurgePlanner();

        $this->assertSame(['cat_p_5'], $planner->forPrices($this->set(new Change(5, false, false, ['listing']))));
        $this->assertSame(['cat_c_12'], $planner->forCategories($this->set(
            new Change(12, false, false, ['page']),
            new Change(13, false, false, ['internal'])
        )));
    }

    private function set(Change ...$changes): ChangeSet
    {
        $set = new ChangeSet();

        foreach ($changes as $change) {
            $set->add($change);
        }

        return $set;
    }
}
