<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Layer;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\CollectionFilterInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Marks the product collection a listing page shows, before any attribute is selected on it.
 */
abstract class MarkListingCollection
{
    public function __construct(
        private readonly PageScope $scope
    ) {
    }

    /**
     * Which page the marked collection belongs to.
     */
    abstract protected function page(): PageType;

    /**
     * @return mixed[]|null Null leaves the arguments as they were.
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function beforeFilter(CollectionFilterInterface $subject, mixed $collection, Category $category): ?array
    {
        if ($collection instanceof Collection) {
            $this->scope->mark($collection, $this->page());
        }

        return null;
    }
}
