<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\CategoryAttributeCodes;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Model\Read\ReadDecision;
use Kingletas\CatalogIndex\Model\Read\ReadGate;
use Magento\Catalog\Model\ResourceModel\Category\Collection as EavCollection;
use Magento\Catalog\Model\ResourceModel\Category\Flat\Collection as FlatCollection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Before a category collection loads, takes the attributes documents can supply out of its database load.
 */
class SkipDocumentCategoryAttributes implements ObserverInterface
{
    public function __construct(
        private readonly PageScope $scope,
        private readonly ReadGate $gate,
        private readonly CategoryAttributeCodes $codes
    ) {
    }

    /**
     * Every category list a storefront page builds goes through here: the menu, breadcrumbs and the filters.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getEvent()->getData('category_collection');

        if (!$collection instanceof EavCollection && !$collection instanceof FlatCollection) {
            return;
        }

        $page = PageType::CategoryTree;
        $decision = $this->gate->decide($page, (int) $collection->getStoreId());

        if ($decision === ReadDecision::Disabled) {
            return;
        }

        $this->scope->mark($collection, $page);

        // The flat resource selects every column in one query, so no attribute on it is worth skipping.
        if ($decision !== ReadDecision::Allow || !$collection instanceof EavCollection) {
            return;
        }

        $codes = $this->codes->selectedOn($collection);

        if ($codes === []) {
            return;
        }

        foreach ($codes as $code) {
            $collection->removeAttributeToSelect($code);
        }

        $this->scope->skip($collection, $codes);
    }
}
