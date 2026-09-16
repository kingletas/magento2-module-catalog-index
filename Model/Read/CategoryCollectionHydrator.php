<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as EavCollection;
use Magento\Catalog\Model\ResourceModel\Category\Flat\Collection as FlatCollection;

/**
 * Fills a loaded category collection from documents, or reports why it cannot.
 */
class CategoryCollectionHydrator
{
    public function __construct(
        private readonly DocumentReaderInterface $reader,
        private readonly ReadContextResolver $contexts,
        private readonly CategoryHydrator $hydrator,
        private readonly FallbackRecorder $recorder
    ) {
    }

    /**
     * Fills every category or none, so a menu never mixes document and database values.
     */
    public function hydrate(EavCollection|FlatCollection $collection, PageType $page): bool
    {
        $items = array_filter($collection->getItems(), static fn (mixed $item): bool => $item instanceof Category);

        if ($items === []) {
            return true;
        }

        $context = $this->contexts->resolve($page, (int) $collection->getStoreId());

        try {
            $ids = array_map(static fn (Category $item): int => (int) $item->getId(), $items);
            $views = $this->reader->categories($ids, $context);
        } catch (DocumentStoreException) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_STORE_ERROR, count($items));

            return false;
        }

        if (count($views) < count($items)) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_MISSING, count($items));

            return false;
        }

        foreach ($items as $item) {
            $this->hydrator->fillListItem($item, $views[(int) $item->getId()]);
        }

        $this->recorder->served($page, count($items));

        return true;
    }
}
