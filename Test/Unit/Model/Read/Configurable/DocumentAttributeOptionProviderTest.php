<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read\Configurable;

use Kingletas\CatalogIndex\Model\Read\Configurable\DocumentAttributeOptionProvider;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Magento\ConfigurableProduct\Model\AttributeOptionProvider;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\Entity\Attribute\Source\SourceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DocumentAttributeOptionProviderTest extends TestCase
{
    private ServedOptions $served;

    /** @var int Times the database provider was asked. */
    private int $asked = 0;

    protected function setUp(): void
    {
        $this->served = new ServedOptions();
    }

    public function testAServedProductAnswersWithoutTouchingTheDatabase(): void
    {
        $this->served->remember(['93' => [$this->row()]], 7);

        $rows = $this->provider()->getAttributeOptions($this->attribute(), 7);

        $this->assertSame([$this->row()], $rows);
        $this->assertSame(0, $this->asked);
    }

    /**
     * Magento asks by link field, so an id that is only some product's entity id is not an answer.
     */
    public function testAnIdTheRowsWereNotRememberedUnderGoesToTheDatabase(): void
    {
        $this->served->remember(['93' => [$this->row()]], 7);

        $this->provider()->getAttributeOptions($this->attribute(), 5);

        $this->assertSame(1, $this->asked);
    }

    public function testAProductNothingWasServedForGoesToTheDatabase(): void
    {
        $this->provider()->getAttributeOptions($this->attribute(), 7);

        $this->assertSame(1, $this->asked);
    }

    public function testAnotherAttributeOfAServedProductStillGoesToTheDatabase(): void
    {
        $this->served->remember(['93' => [$this->row()]], 7);

        $this->provider()->getAttributeOptions($this->attribute('141'), 7);

        $this->assertSame(1, $this->asked);
    }

    /**
     * An attribute with a source model takes both titles from the source, which is what Magento's own provider does.
     */
    public function testASourceModelSuppliesBothTitles(): void
    {
        $this->served->remember(['93' => [$this->row()]], 7);

        $rows = $this->provider()->getAttributeOptions($this->attribute(source: 'Some\Source'), 7);

        $this->assertSame('Crimson', $rows[0]['default_title']);
        $this->assertSame('Crimson', $rows[0]['option_title']);
    }

    public function testAValueTheSourceDoesNotKnowLosesItsTitleRatherThanKeepingAStaleOne(): void
    {
        $row = ['value_index' => '999'] + $this->row();
        $this->served->remember(['93' => [$row]], 7);

        $rows = $this->provider()->getAttributeOptions($this->attribute(source: 'Some\Source'), 7);

        $this->assertFalse($rows[0]['default_title']);
        $this->assertFalse($rows[0]['option_title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'sku' => 'SKU-5-RED',
            'product_id' => '5',
            'attribute_code' => 'color',
            'value_index' => '49',
            'super_attribute_label' => 'Color',
            'option_title' => 'Red',
            'default_title' => 'Red',
        ];
    }

    private function attribute(string $attributeId = '93', string $source = ''): AbstractAttribute&MockObject
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getAttributeId')->willReturn($attributeId);
        $attribute->method('getSourceModel')->willReturn($source);

        if ($source !== '') {
            $options = $this->createMock(SourceInterface::class);
            $options->method('getAllOptions')->willReturn([['value' => '49', 'label' => 'Crimson']]);
            $attribute->method('getSource')->willReturn($options);
        }

        return $attribute;
    }

    private function provider(): DocumentAttributeOptionProvider
    {
        $database = $this->createMock(AttributeOptionProvider::class);
        $database->method('getAttributeOptions')->willReturnCallback(function (): array {
            $this->asked++;

            return [];
        });

        return new DocumentAttributeOptionProvider($database, $this->served);
    }
}
