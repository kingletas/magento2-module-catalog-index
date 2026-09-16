<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Status;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Model\Rebuild\ChangelogReader;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Kingletas\CatalogIndex\Model\Staging\StagingMode;
use Kingletas\CatalogIndex\Model\Status\StatusReporter;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Framework\MessageQueue\DefaultValueProvider;
use PHPUnit\Framework\TestCase;
use Kingletas\CatalogIndex\Model\Cache\ParkedPurges;

class StatusReporterTest extends TestCase
{
    use ShippedConfig;

    public function testTheSummaryNamesTheLaneAndConnection(): void
    {
        $summary = $this->reporter(new InMemoryDocumentStore())->summary();

        $this->assertSame('queue', $summary['update mode']);
        $this->assertSame('amqp', $summary['queue connection']);
        $this->assertSame('closed', $summary['read breaker']);
        $this->assertSame('3', $summary['scheduled refreshes']);
        $this->assertSame('2', $summary['parked purges']);
    }

    public function testEveryIndexShowsItsLiveBuildDocumentsAndBacklog(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_product_1_x', '5', []);
        $store->pointAlias('kingletas_catalog_product_1', 'kingletas_catalog_product_1_x');

        $rows = $this->reporter($store)->indexes();
        $product = array_values(
            array_filter($rows, static fn (array $row): bool => $row['index'] === 'kingletas_catalog_product_1')
        )[0];

        $this->assertSame('kingletas_catalog_product_1_x', $product['live']);
        $this->assertSame('1', $product['documents']);
        $this->assertSame('2026-09-15T12:00:00+00:00', $product['built']);
        $this->assertSame('7', $product['backlog']);
        $this->assertContains('missing', array_column($rows, 'live'));
        $this->assertContains('not on schedule', array_column($rows, 'backlog'));
    }

    public function testAnUnreachableStoreIsReportedNotThrown(): void
    {
        $store = new InMemoryDocumentStore();
        $store->unreachable = true;

        $rows = $this->reporter($store)->indexes();

        $this->assertStringStartsWith('unreachable: ', $rows[0]['live']);
        $this->assertSame('?', $rows[0]['documents']);
    }

    /**
     * A page reading from the database more often than its budget is the signal the index is not doing its job.
     */
    public function testReadsAreMarkedOverBudgetAboveTheConfiguredShare(): void
    {
        $rows = $this->reporter(new InMemoryDocumentStore())->reads(24);

        $this->assertSame(
            [
                [
                    'page' => 'category_listing',
                    'served' => '90',
                    'fell back' => '10',
                    'ratio' => '10.0%',
                    'verdict' => 'over budget',
                    'top reason' => 'missing_document',
                ],
                [
                    'page' => 'widget',
                    'served' => '100',
                    'fell back' => '0',
                    'ratio' => '0.0%',
                    'verdict' => 'ok',
                    'top reason' => '-',
                ],
            ],
            $rows
        );
    }

    private function reporter(InMemoryDocumentStore $store): StatusReporter
    {
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('scopeIds')->willReturn([1]);
        $state = $this->createMock(StateStorage::class);
        $state->method('get')->willReturnCallback(
            static fn (string $key): ?array => $key === 'build:product:1' ? [
                'built_at' => '2026-09-15T12:00:00+00:00',
            ] : null
        );
        $changelog = $this->createMock(ChangelogReader::class);
        $changelog->method('backlog')->willReturnCallback(
            static fn (IndexFamily $family): ?int => $family === IndexFamily::Product ? 7 : null
        );
        $metrics = $this->createMock(MetricStorage::class);
        $metrics->method('totals')->willReturn([
            'category_listing' => ['served' => 90, 'fallback' => ['store_error' => 2, 'missing_document' => 8]],
            'widget' => ['served' => 100, 'fallback' => []],
        ]);
        $schedule = $this->createMock(ScheduleStorage::class);
        $schedule->method('pending')->willReturn(3);
        $queue = $this->createMock(DefaultValueProvider::class);
        $queue->method('getConnection')->willReturn('amqp');
        $parked = $this->createMock(ParkedPurges::class);
        $parked->method('count')->willReturn(2);

        return new StatusReporter(
            $this->config(),
            $scopes,
            new IndexNamer($this->config()),
            $store,
            $state,
            $changelog,
            $metrics,
            $this->createMock(CircuitBreaker::class),
            $schedule,
            $this->createMock(StagingMode::class),
            $queue,
            $parked
        );
    }
}
