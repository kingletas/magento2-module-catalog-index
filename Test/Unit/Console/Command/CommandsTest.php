<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Console\Command;

use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Console\Command\InspectCommand;
use Kingletas\CatalogIndex\Console\Command\RebuildCommand;
use Kingletas\CatalogIndex\Console\Command\StatusCommand;
use Kingletas\CatalogIndex\Console\Command\VerifyCommand;
use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Drift\DriftReport;
use Kingletas\CatalogIndex\Model\Drift\DriftVerifier;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Rebuild\RebuildReport;
use Kingletas\CatalogIndex\Model\Status\StatusReporter;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\InMemoryDocumentStore;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CommandsTest extends TestCase
{
    use ShippedConfig;

    public function testStatusPrintsTheSummaryIndexesAndReads(): void
    {
        $reporter = $this->createMock(StatusReporter::class);
        $reporter->method('summary')->willReturn(['enabled' => 'yes']);
        $reporter->method('indexes')->willReturn([['index' => 'kingletas_catalog_product_1', 'live' => 'missing']]);
        $reporter->method('reads')->willReturn([]);
        $tester = new CommandTester(new StatusCommand($reporter, 'kingletas:catalog-index:status'));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('kingletas_catalog_product_1', $tester->getDisplay());
        $this->assertStringContainsString('No page has read documents', $tester->getDisplay());
    }

    /**
     * The store does not translate, but a store that does must see every console line go through its dictionary.
     */
    public function testConsoleTextGoesThroughTheTranslator(): void
    {
        $previous = Phrase::getRenderer();
        $renderer = $this->createMock(RendererInterface::class);
        $renderer->method('render')->willReturnCallback(
            static fn (array $source, array $arguments): string => 'T:' . vsprintf(
                str_replace(['%1', '%2', '%3'], '%s', (string) end($source)),
                $arguments
            )
        );
        Phrase::setRenderer($renderer);

        try {
            $verifier = $this->createMock(DriftVerifier::class);
            $verifier->method('verify')->willReturn([]);
            $tester = new CommandTester(new VerifyCommand($verifier, 'kingletas:catalog-index:verify'));
            $tester->execute([]);
        } finally {
            Phrase::setRenderer($previous);
        }

        $this->assertStringContainsString('T:The catalog index is disabled', $tester->getDisplay());
    }

    public function testRebuildRunsEveryFamilyOrRefreshesNamedIds(): void
    {
        $families = [];
        $rebuild = $this->createMock(FullRebuild::class);
        $rebuild->method('run')->willReturnCallback(static function (IndexFamily $family) use (&$families): array {
            $families[] = $family->value;

            return [
                new RebuildReport($family, 1, 'idx', null, 3),
                new RebuildReport($family, 2, skippedBecause: 'busy'),
            ];
        });
        $refresher = $this->createMock(RefresherInterface::class);
        $refresher->method('family')->willReturn(IndexFamily::Stock);
        $refresher->expects($this->once())->method('refresh')->with([4, 9]);
        $tester = new CommandTester(
            new RebuildCommand($rebuild, new RefresherPool(['stock' => $refresher]), 'rebuild')
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSame(['product', 'price', 'stock', 'category'], $families);
        $this->assertStringContainsString('stock 2: skipped, busy', $tester->getDisplay());
        $this->assertSame(Command::SUCCESS, $tester->execute(['--family' => 'stock', '--ids' => '4,9']));
        $this->assertSame(Command::INVALID, $tester->execute(['--family' => 'basket']));
    }

    public function testVerifyFailsWhenDriftIsFound(): void
    {
        $verifier = $this->createMock(DriftVerifier::class);
        $verifier->method('verify')->willReturnOnConsecutiveCalls(
            [new DriftReport(1, 10, [4], true)],
            [new DriftReport(1, 10)],
            []
        );
        $tester = new CommandTester(new VerifyCommand($verifier, 'verify'));

        $this->assertSame(Command::FAILURE, $tester->execute(['--sample' => '10']));
        $this->assertStringContainsString('store 1: 1 of 10 differed (4), rebuilt', $tester->getDisplay());
        $this->assertSame(Command::SUCCESS, $tester->execute(['--no-repair' => true]));
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testInspectNamesTheFieldsThatDiffer(): void
    {
        $store = new InMemoryDocumentStore();
        $store->seed('kingletas_catalog_product_1', '5', ['sku' => 'SKU-5', 'name' => 'Old name'], 3);
        $resource = $this->createMock(ProductResource::class);
        $resource->method('getIdBySku')->willReturnCallback(
            static fn (string $sku): int|false => $sku === 'SKU-5' ? 5 : false
        );
        $builder = $this->createMock(ProductDocumentBuilder::class);
        $builder->method('build')->willReturn(
            new BuildBatch(1, [new Document('5', 1, ['sku' => 'SKU-5', 'name' => 'New name'])])
        );
        $scopes = $this->createMock(ScopeResolver::class);
        $scopes->method('websiteIdOf')->willReturn(1);
        $clock = new FakeClock();
        $tester = new CommandTester(new InspectCommand(
            $resource,
            $builder,
            $store,
            new IndexNamer($this->config()),
            $scopes,
            new VersionSource($clock),
            $clock,
            'inspect'
        ));

        $this->assertSame(Command::FAILURE, $tester->execute(['sku' => 'SKU-5']));
        $this->assertStringContainsString('differs: name', $tester->getDisplay());
        $this->assertStringContainsString('stored version 3', $tester->getDisplay());
        $this->assertSame(Command::INVALID, $tester->execute(['sku' => 'NOPE']));
    }
}
