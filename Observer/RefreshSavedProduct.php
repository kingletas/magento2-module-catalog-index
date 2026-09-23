<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Queues a product and stock refresh once a product save or delete has committed.
 */
class RefreshSavedProduct implements ObserverInterface
{
    public function __construct(
        private readonly RefreshPublisher $publisher
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getData('product');
        $productId = $product instanceof DataObject ? (int) $product->getData('entity_id') : 0;

        if ($productId <= 0) {
            return;
        }

        $this->publisher->publish(IndexFamily::Product, [$productId], RefreshRequest::REASON_PRODUCT_SAVED);
        $this->publisher->publish(IndexFamily::Stock, [$productId], RefreshRequest::REASON_PRODUCT_SAVED);
    }
}
