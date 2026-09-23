<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Drift;

use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Drift\DriftReport;
use Kingletas\CatalogIndex\Model\Drift\DriftVerifier;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\ProductRefresher;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Kingletas\Foundation\Test\Support\FakeClock;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;

class DriftVerifierTest extends TestCase
{
    use ShippedConfig;
    use StubbedDatabase;

    /** @var int[][] */
    private array $repaired = [];

    /**
     * A stored document that differs, one that is missing, and one that should have been removed all count as drift.
     */
    public function testEveryKindOfDriftIsFoundAndRepaired(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_product_1', '1', ['_fp' => ['listing' => 'same']]);
        $store->seed('kingletas_catalog_product_1', '2', ['_fp' => ['listing' => 'stale']]);
        $store->seed('kingletas_catalog_product_1', '4', ['_fp' => ['listing' => 'orphan']]);
        $this->answers['catalog_product_website'] = static fn (array $query): array => in_array(
            'limit',
            array_column($query['calls'], 0),
            true
        )
            ? ['1', '2', '3', '4']
            : ['low' => '1', 'high' => '4'];

        $reports = $this->verifier($store)->verify(4);

        $this->assertSame([2, 3, 4], $reports[0]->drifted);
        $this->assertTrue($reports[0]->repaired);
        $this->assertSame([[2, 3, 4]], $this->repaired);
        $this->assertSame(0.75, $reports[0]->ratio());
    }

    public function testReportingOnlyChangesNothing(): void
    {
        $store = new InMemoryDocumentStore();
        $this->answers['catalog_product_website'] = static fn (array $query): array => in_array(
            'limit',
            array_column($query['calls'], 0),
            true
        )
            ? ['1']
            : ['low' => '1', 'high' => '1'];

        $report = $this->verifier($store)->verify(2, false)[0];

        $this->assertSame([1], $report->drifted);
        $this->assertFalse($report->repaired);
        $this->assertSame([], $this->repaired);
        $this->assertSame(0.0, (new DriftReport(1, 0))->ratio());
    }

    private function verifier(InMemoryDocumentStore $store): DriftVerifier
    {
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('storeIds')->willReturn([1]);
        $scopes->method('websiteIdOf')->willReturn(1);
        $builder = $this->createMock(ProductDocumentBuilder::class);
        $builder->method('build')->willReturnCallback(static fn (
            array $ids,
            BuildContextInterface $context
        ): BuildBatch => new BuildBatch(
            $context->getVersion(),
            array_map(
                static fn (int $id): Document => new Document((string) $id, 1, ['_fp' => ['listing' => 'same']]),
                array_values(array_intersect($ids, [1, 2, 3]))
            ),
            array_values(array_diff($ids, [1, 2, 3]))
        ));
        $refresher = $this->createMock(ProductRefresher::class);
        $refresher->method('refresh')->willReturnCallback(function (array $ids): void {
            $this->repaired[] = $ids;
        });
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturn('UTC');
        $clock = new FakeClock();

        return new DriftVerifier(
            $this->config(),
            $scopes,
            new IndexNamer($this->config()),
            $store,
            $builder,
            $refresher,
            $this->resourceConnection(),
            new VersionSource($clock),
            $clock,
            $timezone
        );
    }
}
