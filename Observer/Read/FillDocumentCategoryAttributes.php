<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryCollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Magento\Catalog\Model\ResourceModel\Category\Collection as EavCollection;
use Magento\Catalog\Model\ResourceModel\Category\Flat\Collection as FlatCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Fills the category values a collection skipped from documents, or loads them from the database after all.
 */
class FillDocumentCategoryAttributes implements ObserverInterface
{
    public function __construct(
        private readonly PageScope $scope,
        private readonly ReadGate $gate,
        private readonly CategoryCollectionHydrator $hydrator,
        private readonly FallbackRecorder $recorder
    ) {
    }

    /**
     * The skipped codes go back on the select either way, so later code asking isAttributeAdded() sees no difference.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getEvent()->getData('category_collection');

        $isCategories = $collection instanceof EavCollection || $collection instanceof FlatCollection;

        if (!$isCategories || $this->scope->pageOf($collection) !== PageType::CategoryTree) {
            return;
        }

        $codes = $this->scope->takeSkipped($collection) ?? [];

        if ($codes !== [] && $collection instanceof EavCollection) {
            $collection->addAttributeToSelect($codes);
        }

        if ($this->served($collection)) {
            return;
        }

        if ($codes !== [] && $collection instanceof EavCollection) {
            $collection->_loadAttributes();
        }
    }

    private function served(EavCollection|FlatCollection $collection): bool
    {
        $page = PageType::CategoryTree;

        if ($this->gate->decide($page, (int) $collection->getStoreId()) !== ReadDecision::Allow) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_BREAKER_OPEN, count($collection->getItems()));

            return false;
        }

        return $this->hydrator->hydrate($collection, $page);
    }
}
