<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Queues a stock refresh for everything an order, cancellation or refund touched, on the priority lane.
 */
class RefreshOrderedProducts implements ObserverInterface
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
        $source = $observer->getEvent()->getData('order') ?? $observer->getEvent()->getData('creditmemo');

        if (!$source instanceof Order && !$source instanceof Creditmemo) {
            return;
        }

        $ids = [];

        foreach ($source->getAllItems() as $item) {
            if ($item instanceof DataObject && (int) $item->getData('product_id') > 0) {
                $ids[] = (int) $item->getData('product_id');
            }
        }

        if ($ids !== []) {
            $this->publisher->publish(IndexFamily::Stock, $ids, RefreshRequest::REASON_ORDER);
        }
    }
}
