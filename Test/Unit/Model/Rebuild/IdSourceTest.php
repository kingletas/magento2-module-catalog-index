<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Rebuild;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Rebuild\IdSource;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use PHPUnit\Framework\TestCase;

class IdSourceTest extends TestCase
{
    use StubbedDatabase;

    public function testBatchesWalkForwardFromTheLastIdUntilAShortPage(): void
    {
        $pages = [['1', '2'], ['3', '4'], ['5']];
        $this->answers['catalog_product_website'] = static function () use (&$pages): array {
            return array_shift($pages) ?? [];
        };

        $batches = iterator_to_array(
            (new IdSource($this->resourceConnection(), $this->scopes()))->batches(IndexFamily::Product, 1, 2),
            false
        );

        $this->assertSame([[1, 2], [3, 4], [5]], $batches);
        $this->assertSame([0, 2, 4], $this->whereValues('product_id > ?'));
        $this->assertSame([9, 9, 9], $this->whereValues('website_id = ?'));
    }

    public function testAnExactMultipleEndsWithOneEmptyRead(): void
    {
        $pages = [['1', '2']];
        $this->answers['catalog_product_index_price'] = static function () use (&$pages): array {
            return array_shift($pages) ?? [];
        };

        $batches = iterator_to_array(
            (new IdSource($this->resourceConnection(), $this->scopes()))->batches(IndexFamily::Price, 3, 2),
            false
        );

        $this->assertSame([[1, 2]], $batches);
        $this->assertSame([3, 3], $this->whereValues('website_id = ?'));
    }

    private function scopes(): ScopeResolver
    {
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('websiteIdOf')->willReturn(9);
        $scopes->method('rootCategoryOf')->willReturn(2);

        return $scopes;
    }
}
