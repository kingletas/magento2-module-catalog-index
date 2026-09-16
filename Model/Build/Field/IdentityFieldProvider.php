<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build\Field;

use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

/**
 * Who the product is, and whether it is published at all.
 */
class IdentityFieldProvider extends AbstractFieldProvider
{
    /**
     * @inheritDoc
     */
    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void
    {
        if ((int) $product->getData('status') !== Status::STATUS_ENABLED) {
            $draft->exclude('disabled');

            return;
        }

        $draft->set('entity_id', (int) $product->getId(), DocumentDraft::GROUP_LISTING);
        $draft->set('sku', (string) $product->getData('sku'), DocumentDraft::GROUP_LISTING);
        $draft->set('type_id', (string) $product->getTypeId(), DocumentDraft::GROUP_LISTING);
        $draft->set('visibility', (int) $product->getData('visibility'), DocumentDraft::GROUP_LISTING);
        $draft->set('required_options', (int) $product->getData('required_options'), DocumentDraft::GROUP_LISTING);
        $draft->set('attribute_set_id', (int) $product->getData('attribute_set_id'), DocumentDraft::GROUP_DETAIL);
        $draft->set('has_options', (int) $product->getData('has_options'), DocumentDraft::GROUP_DETAIL);
        $draft->set('created_at', (string) $product->getData('created_at'), DocumentDraft::GROUP_INTERNAL);
        $draft->set('updated_at', (string) $product->getData('updated_at'), DocumentDraft::GROUP_INTERNAL);
    }
}
