<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Link;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection;

/**
 * Marks related, up-sell and cross-sell collections, which storefront blocks always order by position.
 */
class MarkLinkedCollection
{
    public function __construct(
        private readonly PageScope $scope
    ) {
    }

    public function afterSetPositionOrder(Collection $subject, mixed $result): mixed
    {
        $this->scope->mark($subject, PageType::LinkedProducts);

        return $result;
    }
}
