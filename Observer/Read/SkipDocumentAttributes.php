<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\DocumentAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Before a marked product collection loads, takes the attributes documents can supply out of its database load.
 */
class SkipDocumentAttributes implements ObserverInterface
{
    public function __construct(
        private readonly PageScope $scope,
        private readonly ReadGate $gate,
        private readonly DocumentAttributeCodes $codes
    ) {
    }

    /**
     * While the breaker is open nothing is skipped, but the collection is noted so its products are counted.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getEvent()->getData('collection');
        $page = $collection instanceof Collection ? $this->scope->pageOf($collection) : null;

        if ($page === null || $collection->isEnabledFlat()) {
            return;
        }

        $decision = $this->gate->decide($page, (int) $collection->getStoreId());

        if ($decision === ReadDecision::BreakerOpen) {
            $this->scope->skip($collection, []);
        }

        $codes = $decision === ReadDecision::Allow ? $this->codes->selectedOn($collection) : [];

        if ($codes === []) {
            return;
        }

        foreach ($codes as $code) {
            $collection->removeAttributeToSelect($code);
        }

        $this->scope->skip($collection, $codes);
    }
}
