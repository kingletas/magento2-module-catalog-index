<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Model\Build\StoredAttributes;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Names the attributes a collection selected that product documents can supply instead of the database.
 */
class DocumentAttributeCodes
{
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly StoredAttributes $storedAttributes,
        private readonly string $galleryCode = 'media_gallery'
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

        foreach ($this->eavConfig->getEntityAttributes(Product::ENTITY) as $code => $attribute) {
            $code = (string) $code;

            if ($collection->isAttributeAdded($code) && $this->isSuppliedByDocuments($code, $attribute)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function isSuppliedByDocuments(string $code, mixed $attribute): bool
    {
        if ($code === $this->galleryCode) {
            return true;
        }

        return $attribute instanceof AbstractAttribute
            && !$attribute->isStatic()
            && $this->storedAttributes->isStored($code);
    }
}
