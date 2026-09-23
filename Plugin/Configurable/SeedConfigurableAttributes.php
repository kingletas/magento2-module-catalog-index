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
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
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
        private readonly LinkField $linkField,
        private readonly FallbackRecorder $recorder
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
        $id = (int) $product->getId();

        if ($id === 0) {
            return null;
        }

        if ($product->hasData(self::CONFIGURABLE_ATTRIBUTES)) {
            $this->countPreemption($id);

            return null;
        }

        $view = $this->served->forProduct($id);

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
            $this->recorder->attributesServed();
        }

        return null;
    }

    /**
     * Counted once per product, so status shows a document that went unused because another plugin answered first.
     */
    private function countPreemption(int $id): void
    {
        if ($this->served->notePreempted($id)) {
            $this->recorder->attributesPreempted();
        }
    }
}
