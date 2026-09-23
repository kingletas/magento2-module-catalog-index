<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Layer;

use Kingletas\CatalogIndex\Api\Data\PageType;

/**
 * Marks the product collection a search results page lists.
 */
class MarkSearchListing extends MarkListingCollection
{
    /**
     * @inheritDoc
     */
    protected function page(): PageType
    {
        return PageType::SearchListing;
    }
}
