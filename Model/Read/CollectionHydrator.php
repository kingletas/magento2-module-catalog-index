<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
use Kingletas\CatalogIndex\Api\DocumentReaderInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Fills a loaded product collection's attributes from documents, or reports why it cannot.
 */
class CollectionHydrator
{
    public function __construct(
        private readonly DocumentReaderInterface $reader,
        private readonly ReadContextResolver $contexts,
        private readonly ProductHydrator $hydrator,
        private readonly FallbackRecorder $recorder
    ) {
    }

    /**
     * Hydrates every item or none, so a page never mixes document and database values.
     */
    public function hydrate(Collection $collection, PageType $page): bool
    {
        $items = array_filter($collection->getItems(), static fn (mixed $item): bool => $item instanceof Product);

        if ($items === []) {
            return true;
        }

        $context = $this->contexts->resolve($page, (int) $collection->getStoreId());

        try {
            $ids = array_map(static fn (Product $item): int => (int) $item->getId(), $items);
            $views = $this->reader->products($ids, $context);
        } catch (DocumentStoreException) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_STORE_ERROR, count($items));

            return false;
        }

        if (count($views) < count($items)) {
            $this->recorder->fellBack($page, FallbackRecorder::REASON_MISSING, count($items));

            return false;
        }

        foreach ($items as $item) {
            $this->hydrator->fillListingItem($item, $views[(int) $item->getId()], $context->getStoreId());
        }

        $collection->setFlag('media_gallery_added', true);
        $this->recorder->served($page, count($items));

        return true;
    }
}
