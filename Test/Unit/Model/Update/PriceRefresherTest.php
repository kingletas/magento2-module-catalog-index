<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\PriceDocumentBuilder;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\PriceRefresher;

class PriceRefresherTest extends RefresherTestCase
{
    public function testPricesAreWrittenPerWebsiteAndChangedProductsPurged(): void
    {
        $builder = $this->createMock(PriceDocumentBuilder::class);
        $builder->method('build')->willReturnCallback(
            static fn (array $ids, int $websiteId, int $version): BuildBatch => new BuildBatch($version, [
                new Document('5', $version, ['_fp' => ['listing' => 'p']]),
            ], [6])
        );
        $refresher = new PriceRefresher(
            $this->config(),
            $this->scopes(),
            $this->namer(),
            $builder,
            $this->writer(),
            new PurgePlanner(),
            $this->purger,
            $this->versions()
        );

        $refresher->refresh([5, 6]);

        $this->assertSame(IndexFamily::Price, $refresher->family());
        $this->assertArrayHasKey('5', $this->store->indexes['kingletas_catalog_price_1']);
        $this->assertSame(['cat_p_5'], $this->purger->tags());
    }
}
