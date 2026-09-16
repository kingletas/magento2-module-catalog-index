<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Names the category attributes a collection selected that category documents can supply instead of the database.
 */
class CategoryAttributeCodes
{
    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * Static attributes come with the entity row at no cost, so only table-backed ones are worth skipping.
     *
     * @return string[]
     */
    public function selectedOn(Collection $collection): array
    {
        $codes = [];

        foreach ($this->eavConfig->getEntityAttributes(Category::ENTITY) as $code => $attribute) {
            $code = (string) $code;

            if ($collection->isAttributeAdded($code) && $attribute instanceof AbstractAttribute
                && !$attribute->isStatic()
            ) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
