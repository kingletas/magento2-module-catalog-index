<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Mview;

use Magento\Catalog\Api\Data\CategoryInterface;

/**
 * The change-log trigger for category attribute tables, which join on the category's link field.
 */
class CategoryLinkFieldSubscription extends LinkFieldSubscription
{
    /**
     * @inheritDoc
     */
    protected function entityInterface(): string
    {
        return CategoryInterface::class;
    }
}
