<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read\Entity;

use Kingletas\CatalogIndex\Model\Read\CategoryHydrator;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\DetailDocuments;
use Kingletas\CatalogIndex\Model\Read\Entity\CategoryAttributeReader;
use Kingletas\CatalogIndex\Model\Read\Entity\ProductAttributeReader;
use Kingletas\CatalogIndex\Model\Read\Entity\ServedProductExtension;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Test\Support\ProductDoubles;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\Operation\AttributeInterface;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;
use PHPUnit\Framework\TestCase;

class EntityReadersTest extends TestCase
{
    use LinkFieldDouble;

    use ProductDoubles;
    use ShippedConfig;

    private ?ProductView $productView = null;

    private ?CategoryView $categoryView = null;

    /** @var array<int, array{0: int, 1: int}> */
    private array $asked = [];

    public function testAProductNotServedIsReadFromTheDatabase(): void
    {
        $data = $this->productReader()->execute(ProductInterface::class, ['entity_id' => 5, 'store_id' => 2]);

        $this->assertSame(['entity_id' => 5, 'store_id' => 2, 'name' => 'From the database'], $data);
        $this->assertSame([[5, 2]], $this->asked);
    }

    public function testAServedProductIsReadFromItsDocument(): void
    {
        $this->productView = new ProductView(5, ['listing_attributes' => ['name' => 'From the document']]);

        $data = $this->productReader()->execute(ProductInterface::class, ['entity_id' => 5, 'store_id' => 2]);

        $this->assertSame('From the document', $data['name']);
    }

    /**
     * The admin loads products in edit mode, and an edit must start from the database.
     */
    public function testEditModeNeverAsksForADocument(): void
    {
        $this->productView = new ProductView(5, ['listing_attributes' => ['name' => 'From the document']]);

        $data = $this->productReader()->execute(ProductInterface::class, ['entity_id' => 5, '_edit_mode' => true]);

        $this->assertSame('From the database', $data['name']);
        $this->assertSame([], $this->asked);
    }

    public function testACategoryIsReadFromItsDocumentOnlyWhenServed(): void
    {
        $reader = $this->categoryReader();

        $this->assertSame('From the database', $reader->execute('category', ['entity_id' => 12])['name']);

        $this->categoryView = new CategoryView(12, ['attributes' => ['name' => 'From the document']]);

        $this->assertSame('From the document', $reader->execute('category', ['entity_id' => 12])['name']);
    }

    public function testASkippedExtensionRunsForAnyProductNotServed(): void
    {
        $product = $this->product(['entity_id' => 5, 'store_id' => 1]);

        $this->extension()->execute($product);

        $this->assertSame('loaded', $product->getData('options'));
    }

    public function testAServedProductSkipsTheExtensionAndGetsItsServedData(): void
    {
        $this->productView = new ProductView(5, []);
        $product = $this->product(['entity_id' => 5, 'store_id' => 1]);

        $this->assertSame($product, $this->extension()->execute($product));
        $this->assertSame([], $product->getData('options'));
    }

    private function productReader(): ProductAttributeReader
    {
        return new ProductAttributeReader(
            $this->databaseReader(),
            $this->documents(),
            new ProductHydrator(
                $this->config(),
                new ServedOptions(),
                $this->linkField(),
                new ServedAttributes()
            )
        );
    }

    private function categoryReader(): CategoryAttributeReader
    {
        return new CategoryAttributeReader($this->databaseReader(), $this->documents(), new CategoryHydrator());
    }

    private function extension(): ServedProductExtension
    {
        $inner = $this->createMock(ExtensionInterface::class);
        $inner->method('execute')->willReturnCallback(static function (object $entity): object {
            $entity->setData('options', 'loaded');

            return $entity;
        });

        return new ServedProductExtension($inner, $this->documents(), ['options' => []]);
    }

    private function databaseReader(): AttributeInterface
    {
        $reader = $this->createMock(AttributeInterface::class);
        $reader->method('execute')->willReturnCallback(
            static fn (string $type, array $data): array => $data + ['name' => 'From the database']
        );

        return $reader;
    }

    private function documents(): DetailDocuments
    {
        $documents = $this->createMock(DetailDocuments::class);
        $documents->method('product')->willReturnCallback(function (int $id, int $storeId): ?ProductView {
            $this->asked[] = [$id, $storeId];

            return $this->productView;
        });
        $documents->method('category')->willReturnCallback(fn (): ?CategoryView => $this->categoryView);

        return $documents;
    }
}
