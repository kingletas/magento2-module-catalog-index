<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Api\StockReaderInterface;
use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Stock\StockLevel;
use Kingletas\CatalogIndex\Model\Stock\StockReaderPool;
use Kingletas\CatalogIndex\Model\Update\AffectedProductResolver;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class StockDocumentBuilderTest extends TestCase
{
    use ShippedConfig;

    /** @var int[][] */
    private array $reads = [];

    public function testAParentCarriesWhetherEachVariantCanBeBoughtFromOneRead(): void
    {
        $batch = $this->builder('0')->build([5], 1, 7);

        $this->assertSame([[5, 51, 52]], $this->reads);
        $this->assertTrue($batch->documents[0]->get('is_salable'));
        $this->assertSame([51 => true, 52 => false], $batch->documents[0]->get('children'));
    }

    public function testLowStockIsOnlyFlaggedWhenAThresholdIsSet(): void
    {
        $this->assertFalse($this->builder('0')->build([51], 1, 7)->documents[0]->get('low_stock'));
        $this->assertTrue($this->builder('5')->build([51], 1, 7)->documents[0]->get('low_stock'));
    }

    public function testAProductWithNoStockRecordIsRemoved(): void
    {
        $this->assertSame([99], $this->builder('0')->build([99], 1, 7)->removedIds);
    }

    private function builder(string $threshold): StockDocumentBuilder
    {
        $reader = $this->createMock(StockReaderInterface::class);
        $reader->method('isApplicable')->willReturn(true);
        $reader->method('read')->willReturnCallback(function (array $ids): array {
            $this->reads[] = $ids;
            $levels = [
                5 => new StockLevel(5, 0.0, 3.0, true, 1),
                51 => new StockLevel(51, 3.0, 3.0, true, 1),
                52 => new StockLevel(52, 0.0, 0.0, false, 1),
            ];

            return array_intersect_key($levels, array_flip($ids));
        });
        $relations = $this->createMock(AffectedProductResolver::class);
        $relations->method('childrenOf')->willReturnCallback(
            static fn (array $ids): array => in_array(5, $ids, true) ? [5 => [51, 52]] : []
        );

        return new StockDocumentBuilder(
            new StockReaderPool(['only' => $reader]),
            $relations,
            new FingerprintCalculator(),
            $this->config(['purge/low_stock_threshold' => $threshold])
        );
    }
}
