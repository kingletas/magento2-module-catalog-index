<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Api\Data\CategoryViewInterface;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Api\Data\ProductViewInterface;
use Kingletas\CatalogIndex\Api\Data\ReadContextInterface;
use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\DetailDocuments;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Read\ReadContextResolver;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use PHPUnit\Framework\TestCase;

class DetailDocumentsTest extends TestCase
{
    private PageScope $scope;

    /** @var string[] */
    private array $outcomes = [];

    private int $reads = 0;

    protected function setUp(): void
    {
        $this->scope = new PageScope();
    }

    /**
     * Add to cart loads the same product in the same request, and must always get the database's answer.
     */
    public function testOnlyTheProductThePageIsForIsServed(): void
    {
        $documents = $this->documents(['type_id' => 'simple', 'has_custom_options' => false]);

        $this->assertNull($documents->product(5, 1));

        $this->scope->enter(PageType::ProductView, 5);

        $this->assertInstanceOf(ProductViewInterface::class, $documents->product(5, 1));
        $this->assertNull($documents->product(6, 1));

        $this->scope->leave(PageType::ProductView);

        $this->assertNull($documents->product(5, 1));
    }

    /**
     * The attribute reader and each skipped extension ask about the same product; the store is read once.
     */
    public function testTheDecisionIsTakenOncePerProductPerRequest(): void
    {
        $this->scope->enter(PageType::ProductView, 5);
        $documents = $this->documents(['type_id' => 'simple', 'has_custom_options' => false]);

        $documents->product(5, 1);
        $documents->product(5, 1);
        $documents->product(5, 1);

        $this->assertSame(1, $this->reads);
        $this->assertSame(['served'], $this->outcomes);
    }

    public function testUnsupportedMissingOrUnreachableDocumentsFallBackWithAReason(): void
    {
        $this->scope->enter(PageType::ProductView, 5);

        $this->documents(['type_id' => 'bundle', 'has_custom_options' => false])->product(5, 1);
        $this->documents(null)->product(5, 1);
        $this->documents(false)->product(5, 1);
        $this->documents([], ReadDecision::BreakerOpen)->product(5, 1);
        $this->documents([], ReadDecision::Disabled)->product(5, 1);

        $this->assertSame(['unsupported_product', 'missing_document', 'store_error', 'breaker_open'], $this->outcomes);
    }

    public function testACategoryPageIsServedOnlyForItsOwnCategory(): void
    {
        $documents = $this->documents(['type_id' => 'simple']);

        $this->assertNull($documents->category(12, 1));

        $this->scope->enter(PageType::CategoryView, 12);

        $this->assertInstanceOf(CategoryViewInterface::class, $documents->category(12, 1));
        $this->assertNull($documents->category(13, 1));
        $this->assertSame(['served'], $this->outcomes);
    }

    public function testAMissingCategoryDocumentFallsBack(): void
    {
        $this->scope->enter(PageType::CategoryView, 12);

        $this->assertNull($this->documents(null)->category(12, 1));
        $this->assertNull($this->documents(false)->category(12, 1));
        $this->assertSame(['missing_document', 'store_error'], $this->outcomes);
    }

    /**
     * @param array<string, mixed>|false|null $source False makes the store fail; null means no document.
     */
    private function documents(array|false|null $source, ReadDecision $decision = ReadDecision::Allow): DetailDocuments
    {
        $gate = $this->createMock(ReadGate::class);
        $gate->method('decide')->willReturn($decision);
        $reader = $this->createMock(DocumentReaderInterface::class);
        $reader->method('products')->willReturnCallback(function () use ($source): array {
            $this->reads++;

            return $this->answer($source, static fn (): ProductViewInterface => new ProductView(
                5,
                (array) $source,
                null,
                ['is_salable' => true]
            ), 5);
        });
        $reader->method('categories')->willReturnCallback(
            fn (): array => $this->answer($source, static fn (): CategoryViewInterface => new CategoryView(12, []), 12)
        );
        $contexts = $this->createMock(ReadContextResolver::class);
        $contexts->method('resolve')->willReturnCallback(
            static fn (PageType $page): ReadContextInterface => new ReadContext($page, 1, 1, 0)
        );
        $recorder = $this->createMock(FallbackRecorder::class);
        $recorder->method('fellBack')->willReturnCallback(function (PageType $page, string $reason): void {
            $this->outcomes[] = $reason;
        });
        $recorder->method('served')->willReturnCallback(function (): void {
            $this->outcomes[] = 'served';
        });

        return new DetailDocuments($this->scope, $gate, $reader, $contexts, $recorder);
    }

    /**
     * @param array<string, mixed>|false|null $source
     * @return array<int, object>
     */
    private function answer(array|false|null $source, callable $view, int $id): array
    {
        if ($source === false) {
            throw new DocumentStoreException('The document store did not answer.');
        }

        return $source === null ? [] : [$id => $view()];
    }
}
