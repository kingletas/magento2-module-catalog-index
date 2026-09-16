<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Queues every configurable using a saved attribute, because an option label change touches no product row.
 */
class RefreshSuperAttributeProducts implements ObserverInterface
{
    public function __construct(
        private readonly RefreshPublisher $publisher,
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField
    ) {
    }

    /**
     * Swatch labels and option order live in the attribute, so nothing in the product change log ever reports this.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $attribute = $observer->getEvent()->getData('attribute');
        $attributeId = $attribute instanceof DataObject ? (int) $attribute->getData('attribute_id') : 0;

        if ($attributeId <= 0) {
            return;
        }

        $productIds = $this->configurablesUsing($attributeId);

        if ($productIds === []) {
            return;
        }

        $this->publisher->publish(IndexFamily::Product, $productIds, RefreshRequest::REASON_ATTRIBUTE_SAVED);
    }

    /**
     * @return int[]
     */
    private function configurablesUsing(int $attributeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['super' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                []
            )
            ->join(
                ['entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'entity.' . $this->linkField->product() . ' = super.product_id',
                ['entity_id']
            )
            ->where('super.attribute_id = ?', $attributeId)
            ->distinct();

        return array_map('intval', $connection->fetchCol($select));
    }
}
