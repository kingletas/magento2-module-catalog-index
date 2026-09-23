<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Widget;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Marks the collection a CMS product list widget renders.
 */
class MarkWidgetCollection
{
    public function __construct(
        private readonly PageScope $scope
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterCreateCollection(object $subject, mixed $result): mixed
    {
        if ($result instanceof Collection) {
            $this->scope->mark($result, PageType::Widget);
        }

        return $result;
    }
}
