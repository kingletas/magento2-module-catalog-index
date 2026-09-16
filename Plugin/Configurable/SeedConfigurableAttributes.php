<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Configurable;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Read\Configurable\AttributeCollectionBuilder;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Hands Magento a configurable's attribute collection from its document at the moment Magento asks for it.
 */
class SeedConfigurableAttributes
{
    private const string CONFIGURABLE_ATTRIBUTES = '_cache_instance_configurable_attributes';

    public function __construct(
        private readonly ServedAttributes $served,
        private readonly AttributeCollectionBuilder $builder,
        private readonly LinkField $linkField
    ) {
    }

    /**
     * Built on demand rather than when the page loads, because most surfaces never ask and building costs a query.
     *
     * @return mixed[]|null Always null, which tells Magento to keep its own arguments.
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function beforeGetConfigurableAttributes(Configurable $subject, Product $product): ?array
    {
        if (!$product->getId() || $product->hasData(self::CONFIGURABLE_ATTRIBUTES)) {
            return null;
        }

        $view = $this->served->forProduct((int) $product->getId());

        if ($view === null) {
            return null;
        }

        $collection = $this->builder->build(
            $view,
            (int) $product->getData($this->linkField->product()),
            (int) $product->getStoreId()
        );

        if ($collection !== null) {
            $product->setData(self::CONFIGURABLE_ATTRIBUTES, $collection);
        }

        return null;
    }
}
