<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use DateTimeImmutable;
use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\FieldProviderInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Model\Build\FingerprintCalculator;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProductDocumentBuilderTest extends TestCase
{
    use ProductDoubles;

    /** @var string[] */
    private array $events = [];

    private int $loads = 0;

    public function testEveryProviderPreparesOnceThenContributesInSortOrder(): void
    {
        $batch = $this->builder([$this->provider('second', 20), $this->provider('first', 10)])
            ->build([2, 1, 2], $this->context());

        $this->assertSame(1, $this->loads);
        $this->assertSame(
            ['prepare:first', 'prepare:second', 'contribute:first:1', 'contribute:second:1',
                'contribute:first:2', 'contribute:second:2', 'reset:first', 'reset:second'],
            $this->events
        );
        $this->assertSame(['1', '2'], $batch->documentIds());
        $this->assertSame('first', $batch->documents[0]->get('first'));
    }

    /**
     * A product the store no longer loads, or one a provider excludes, must leave the index rather than linger.
     */
    public function testMissingAndExcludedProductsAreRemoved(): void
    {
        $excluder = $this->provider('gate', 5, excludeId: 2);

        $batch = $this->builder([$excluder])->build([1, 2, 3], $this->context());

        $this->assertSame(['1'], $batch->documentIds());
        $this->assertSame([2, 3], $batch->removedIds);
    }

    public function testRefreshMomentsAreCarriedPerProduct(): void
    {
        $batch = $this->builder([$this->provider('dates', 5, refreshAt: '@2000000000')])->build([1], $this->context());

        $this->assertSame([1, 2], array_keys($batch->refreshMoments));
    }

    public function testProvidersAreReleasedEvenWhenOneThrows(): void
    {
        $failing = $this->createMock(FieldProviderInterface::class);
        $failing->method('contribute')->willThrowException(new \RuntimeException('boom'));
        $failing->expects($this->once())->method('resetBatch');

        $this->expectException(\RuntimeException::class);

        $this->builder([$failing])->build([1], $this->context());
    }

    public function testAnEmptyRequestLoadsNothing(): void
    {
        $this->assertSame([], $this->builder([])->build([0, -1], $this->context())->documents);
        $this->assertSame(0, $this->loads);
    }

    public function testAProviderOfTheWrongTypeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProductDocumentBuilder(
            $this->createMock(CollectionFactory::class),
            new FingerprintCalculator(),
            new NullLogger(),
            ['x' => new \stdClass()]
        );
    }

    /**
     * @param FieldProviderInterface[] $providers
     */
    private function builder(array $providers): ProductDocumentBuilder
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getItems')->willReturnCallback(function (): array {
            $this->loads++;

            return [$this->product(['entity_id' => 2]), $this->product(['entity_id' => 1])];
        });
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new ProductDocumentBuilder($factory, new FingerprintCalculator(), new NullLogger(), $providers);
    }

    private function provider(
        string $name,
        int $order,
        int $excludeId = 0,
        ?string $refreshAt = null
    ): FieldProviderInterface {
        $provider = $this->createMock(FieldProviderInterface::class);
        $provider->method('getSortOrder')->willReturn($order);
        $provider->method('prepareBatch')->willReturnCallback(function () use ($name): void {
            $this->events[] = 'prepare:' . $name;
        });
        $provider->method('resetBatch')->willReturnCallback(function () use ($name): void {
            $this->events[] = 'reset:' . $name;
        });
        $provider->method('contribute')->willReturnCallback(
            function (Product $product, DocumentDraft $draft) use ($name, $excludeId, $refreshAt): void {
                $this->events[] = 'contribute:' . $name . ':' . $product->getId();
                $draft->set($name, $name, DocumentDraft::GROUP_LISTING);

                if ((int) $product->getId() === $excludeId) {
                    $draft->exclude('disabled');
                }

                if ($refreshAt !== null) {
                    $draft->refreshAt(new DateTimeImmutable($refreshAt));
                }
            }
        );

        return $provider;
    }

    private function context(): BuildContext
    {
        return new BuildContext(1, 1, 10, new DateTimeImmutable('2026-09-15 12:00:00'));
    }
}
