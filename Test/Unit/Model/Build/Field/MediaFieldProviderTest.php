<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\Field\MediaFieldProvider;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

class MediaFieldProviderTest extends FieldProviderTestCase
{
    /**
     * Staged installs join the gallery on row_id, so the lookup must use the product's link value, not its id.
     */
    public function testImagesAreKeyedByTheLinkFieldAndSortedByPosition(): void
    {
        $this->answers['catalog_product_entity_media_gallery'] = [
            [
                'link_id' => '900',
                'value_id' => '2',
                'file' => '/b.jpg',
                'media_type' => 'image',
                'label' => null,
                'position' => '2',
                'disabled' => '0',
            ],
            [
                'link_id' => '900',
                'value_id' => '1',
                'file' => '/a.jpg',
                'media_type' => 'image',
                'label' => 'Front',
                'position' => '1',
                'disabled' => '0',
            ],
        ];
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getId')->willReturn(90);
        $eav = $this->createMock(EavConfig::class);
        $eav->method('getAttribute')->willReturn($attribute);

        $draft = $this->runProvider(
            new MediaFieldProvider($this->resourceConnection(), $this->linkField('row_id'), $eav),
            [$this->product(['entity_id' => 5, 'row_id' => 900])]
        )[5];

        $images = $draft->get('media_gallery')['images'];
        $this->assertSame([1, 2], array_keys($images));
        $this->assertSame('Front', $images[1]['label']);
        // Magento renders these into the gallery's JSON, so the document keeps the database's string shape.
        $this->assertSame('1', $images[1]['value_id']);
        $this->assertSame('1', $images[1]['position']);
        $this->assertSame('0', $images[1]['disabled']);
        $this->assertContains([900], $this->whereValues('e.row_id IN (?)'));
    }
}
