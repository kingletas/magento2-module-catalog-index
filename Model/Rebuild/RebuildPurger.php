<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Rebuild;

use Kingletas\CatalogIndex\Api\CachePurgerInterface;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;

/**
 * Purges only what a rebuild found different from the index it replaced.
 */
class RebuildPurger
{
    public function __construct(
        private readonly PurgePlanner $planner,
        private readonly CachePurgerInterface $purger,
        private readonly AffectedProductResolver $relations
    ) {
    }

    public function purge(IndexFamily $family, ChangeSet $changes): void
    {
        $tags = match ($family) {
            IndexFamily::Product => $this->planner->forProducts($changes),
            IndexFamily::Price => $this->planner->forPrices($changes),
            IndexFamily::Category => $this->planner->forCategories($changes),
            IndexFamily::Stock => $this->planner->forStock(
                $changes,
                $this->relations->categoriesOf($this->planner->salabilityFlips($changes))
            ),
        };

        $this->purger->purge($tags);
    }
}
