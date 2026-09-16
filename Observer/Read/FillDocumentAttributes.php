<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\CollectionHydrator;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageScope;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Fills the attributes a collection skipped from documents, or loads them from the database after all.
 */
class FillDocumentAttributes implements ObserverInterface
{
    public function __construct(
        private readonly PageScope $scope,
        private readonly CollectionHydrator $hydrator,
        private readonly FallbackRecorder $recorder,
        private readonly string $galleryCode = 'media_gallery'
    ) {
    }

    /**
     * The skipped codes go back on the select either way, so later code asking isAttributeAdded() sees no difference.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getEvent()->getData('collection');
        $codes = $collection instanceof Collection ? $this->scope->takeSkipped($collection) : null;
        $page = $codes === null ? null : $this->scope->pageOf($collection);

        if ($page === null) {
            return;
        }

        if ($codes === []) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_BREAKER_OPEN, count($collection->getItems()));

            return;
        }

        $collection->addAttributeToSelect($codes);

        if ($this->hydrator->hydrate($collection, $page)) {
            return;
        }

        $collection->_loadAttributes();

        if (in_array($this->galleryCode, $codes, true)) {
            $collection->addMediaGalleryData();
        }
    }
}
