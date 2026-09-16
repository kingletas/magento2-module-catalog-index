<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\StockRefresher;

class StockRefresherTest extends RefresherTestCase
{
    private bool $salable = true;

    /**
     * Selling the last unit takes the product off category pages that hide out-of-stock products.
     */
    public function testASalabilityFlipPurgesItsCategoryPages(): void
    {
        $this->refresher()->refresh([51]);
        $this->purger->purges = [];
        $this->salable = false;
        $this->clock->advance('+1 second');

        $this->refresher()->refresh([51]);

        $this->assertSame(['cat_c_p_12', 'cat_p_5', 'cat_p_51'], $this->purger->tags());
    }

    private function refresher(): StockRefresher
    {
        $builder = $this->createMock(StockDocumentBuilder::class);
        $builder->method('build')->willReturnCallback(
            fn (array $ids, int $websiteId, int $version): BuildBatch => new BuildBatch($version, array_map(
                fn (int $id): Document => new Document((string) $id, $version, [
                    '_fp' => ['salable' => $this->salable ? 'yes' : 'no'],
                ]),
                $ids
            ))
        );

        return new StockRefresher(
            $this->config(),
            $this->scopes(),
            $this->namer(),
            $builder,
            $this->writer(),
            $this->relations([51 => [5]], [5 => [12], 51 => [12]]),
            new PurgePlanner(),
            $this->purger,
            $this->versions()
        );
    }
}
