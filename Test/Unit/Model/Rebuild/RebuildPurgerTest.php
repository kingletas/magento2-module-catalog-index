<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Rebuild;

use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\RebuildPurger;
use Kingletas\CatalogIndex\Model\Rebuild\RebuildReport;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use Kingletas\CatalogIndex\Test\Support\RecordingPurger;
use PHPUnit\Framework\TestCase;

class RebuildPurgerTest extends TestCase
{
    public function testEachFamilyPurgesWhatItsPlanSays(): void
    {
        $recorder = new RecordingPurger();
        $relations = $this->createMock(AffectedProductResolver::class);
        $relations->method('categoriesOf')->willReturn([5 => [12]]);
        $purger = new RebuildPurger(new PurgePlanner(), $recorder, $relations);
        $set = new ChangeSet();
        $set->add(new Change(5, false, false, ['salable', 'page']));

        $purger->purge(IndexFamily::Stock, $set);
        $purger->purge(IndexFamily::Category, $set);

        $this->assertSame([['cat_c_p_12', 'cat_p_5'], ['cat_c_5']], $recorder->purges);
        $this->assertFalse((new RebuildReport(IndexFamily::Price, 1))->isSkipped());
    }
}
