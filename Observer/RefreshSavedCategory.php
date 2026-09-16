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

/**
 * Queues a category refresh once a category save or delete has committed.
 */
class RefreshSavedCategory implements ObserverInterface
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
        $category = $observer->getEvent()->getData('category');
        $categoryId = $category instanceof DataObject ? (int) $category->getData('entity_id') : 0;

        if ($categoryId > 0) {
            $this->publisher->publish(IndexFamily::Category, [$categoryId], RefreshRequest::REASON_CATEGORY_SAVED);
        }
    }
}
