<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\GraphQl;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogGraphQl\Model\Resolver\Products\DataProvider\Product\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * Marks the collection a GraphQL products query loads.
 */
class MarkDocumentCollection implements CollectionProcessorInterface
{
    public function __construct(
        private readonly PageScope $scope
    ) {
    }

    /**
     * @inheritDoc
     * @param string[] $attributeNames
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function process(
        Collection $collection,
        SearchCriteriaInterface $searchCriteria,
        array $attributeNames,
        ?ContextInterface $context = null
    ): Collection {
        $this->scope->mark($collection, PageType::GraphQl);

        return $collection;
    }
}
