<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Configurable;

use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\Attribute;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable\AttributeFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Builds the configurable attribute collection Magento would otherwise load once per product on the page.
 */
class AttributeCollectionBuilder
{
    public function __construct(
        private readonly SeededAttributeCollectionFactory $collectionFactory,
        private readonly AttributeFactory $attributeFactory,
        private readonly EavConfig $eavConfig,
        private readonly ConfigurableResource $configurableResource
    ) {
    }

    /**
     * Null when the document cannot supply every row, because half a collection is worse than none.
     *
     * @param int $linkId The parent's link field value, which is what Magento keys super attributes by.
     */
    public function build(ConfigurableViewInterface $view, int $linkId, int $storeId): ?SeededAttributeCollection
    {
        $rows = $view->superAttributes();

        if ($rows === [] || $linkId === 0) {
            return null;
        }

        $attributes = [];

        foreach ($rows as $row) {
            $attribute = $this->attribute($row, $linkId);

            if ($attribute === null) {
                return null;
            }

            $attributes[] = $attribute;
        }

        $collection = $this->collectionFactory->create();
        $collection->seed($attributes, $storeId);

        return $collection;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function attribute(array $row, int $linkId): ?Attribute
    {
        $attributeId = (int) ($row['attribute_id'] ?? 0);
        $superAttributeId = (int) ($row['super_attribute_id'] ?? 0);

        if ($attributeId === 0 || $superAttributeId === 0) {
            return null;
        }

        $eavAttribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeId);

        if (!$eavAttribute->getId()) {
            return null;
        }

        $options = $this->options($eavAttribute, $linkId, $superAttributeId);
        $attribute = $this->attributeFactory->create();
        $attribute->setData([
            'product_super_attribute_id' => $superAttributeId,
            'product_id' => $linkId,
            'attribute_id' => $attributeId,
            // The database hands these back as strings and the swatch JSON carries them verbatim, so a
            // seeded collection has to as well or a strict comparison in the front end stops matching.
            'position' => (string) ($row['position'] ?? 0),
            'label' => $row['label'] ?? null,
            'use_default' => isset($row['use_default']) ? (string) $row['use_default'] : null,
            'product_attribute' => $eavAttribute,
            'options_map' => $options,
            'options' => array_values($options),
        ]);

        return $attribute;
    }

    /**
     * The same map Magento builds in its own loadOptions, from the same rows its own option query returns.
     *
     * @return array<string, array<string, mixed>>
     */
    private function options(AbstractAttribute $eavAttribute, int $linkId, int $superAttributeId): array
    {
        $values = [];

        foreach ($this->configurableResource->getAttributeOptions($eavAttribute, $linkId) as $option) {
            $values[$superAttributeId . ':' . $option['value_index']] = [
                'value_index' => $option['value_index'],
                'label' => $option['option_title'],
                'product_super_attribute_id' => $superAttributeId,
                'default_label' => $option['default_title'],
                'store_label' => $option['default_title'],
                'use_default_value' => true,
            ];
        }

        return $values;
    }
}
