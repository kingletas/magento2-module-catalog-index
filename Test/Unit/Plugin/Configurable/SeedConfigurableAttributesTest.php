<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Plugin\Configurable;

use Kingletas\CatalogIndex\Model\Read\Configurable\AttributeCollectionBuilder;
use Kingletas\CatalogIndex\Model\Read\Configurable\SeededAttributeCollection;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\ConfigurableView;
use Kingletas\CatalogIndex\Plugin\Configurable\SeedConfigurableAttributes;
use Kingletas\CatalogIndex\Test\Support\LinkFieldDouble;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

class SeedConfigurableAttributesTest extends TestCase
{
    use LinkFieldDouble;

    private const string KEY = '_cache_instance_configurable_attributes';

    /** @var int How many collections were built. */
    private int $built = 0;

    public function testAServedProductIsSeededWhenMagentoAsks(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 5);
        $product = $this->product(5);

        $this->plugin($served)->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $product);

        $this->assertInstanceOf(SeededAttributeCollection::class, $product->getData(self::KEY));
        $this->assertSame(1, $this->built);
    }

    public function testAProductNothingWasServedForIsLeftToMagento(): void
    {
        $product = $this->product(5);

        $this->plugin(new ServedAttributes())
            ->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $product);

        $this->assertNull($product->getData(self::KEY));
        $this->assertSame(0, $this->built);
    }

    /**
     * Magento asks the same product more than once on a page, and the collection must be built once.
     */
    public function testAProductThatAlreadyHasItsAttributesIsNotBuiltAgain(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 5);
        $product = $this->product(5);
        $product->setData(self::KEY, 'already there');

        $this->plugin($served)->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $product);

        $this->assertSame('already there', $product->getData(self::KEY));
        $this->assertSame(0, $this->built);
    }

    public function testANewProductWithNoIdIsLeftAlone(): void
    {
        $served = new ServedAttributes();
        $served->remember($this->view(), 5);
        $product = $this->product(0);

        $this->plugin($served)->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $product);

        $this->assertSame(0, $this->built);
    }

    public function testArgumentsPassThroughUnchanged(): void
    {
        $this->assertNull(
            $this->plugin(new ServedAttributes())
                ->beforeGetConfigurableAttributes($this->createMock(Configurable::class), $this->product(5))
        );
    }

    private function plugin(ServedAttributes $served): SeedConfigurableAttributes
    {
        $builder = $this->createMock(AttributeCollectionBuilder::class);
        $builder->method('build')->willReturnCallback(function (): SeededAttributeCollection {
            $this->built++;

            return $this->createMock(SeededAttributeCollection::class);
        });

        return new SeedConfigurableAttributes($served, $builder, $this->linkField());
    }

    private function view(): ConfigurableView
    {
        return new ConfigurableView(['super_attributes' => [['attribute_id' => 93, 'super_attribute_id' => 11]]]);
    }

    private function product(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $data = new DataObject(['entity_id' => $id]);
        $product->method('getId')->willReturn($id ?: null);
        $product->method('getStoreId')->willReturn(1);
        $product->method('getData')->willReturnCallback(
            fn (string $key = '', mixed $index = null): mixed => $data->getData($key, $index)
        );
        $product->method('hasData')->willReturnCallback(fn (string $key = ''): bool => $data->hasData($key));
        $product->method('setData')->willReturnCallback(
            function (array|string $key, mixed $value = null) use ($data, $product): Product {
                $data->setData($key, $value);

                return $product;
            }
        );

        return $product;
    }
}
