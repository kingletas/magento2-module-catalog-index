<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Update;

use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\CategoryDocumentBuilder;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\CategoryRefresher;

class CategoryRefresherTest extends RefresherTestCase
{
    public function testCategoriesAreBuiltUnderEachStoresRoot(): void
    {
        $roots = [];
        $builder = $this->createMock(CategoryDocumentBuilder::class);
        $builder->method('build')->willReturnCallback(
            static function (array $ids, int $storeId, int $root, int $version) use (&$roots): BuildBatch {
                $roots[] = $root;

                return new BuildBatch($version, [new Document('12', $version, ['_fp' => ['page' => 'x']])]);
            }
        );
        $refresher = new CategoryRefresher(
            $this->config(),
            $this->scopes(),
            $this->namer(),
            $builder,
            $this->writer(),
            new PurgePlanner(),
            $this->purger,
            $this->versions()
        );

        $refresher->refresh([12]);

        $this->assertSame(IndexFamily::Category, $refresher->family());
        $this->assertSame([2, 2], $roots);
        $this->assertSame(['cat_c_12'], $this->purger->tags());
    }
}
