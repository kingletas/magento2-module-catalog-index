<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * The column that joins attribute tables to an entity, which is row_id where content staging is installed.
 */
class LinkField
{
    public function __construct(
        private readonly MetadataPool $metadataPool
    ) {
    }

    public function product(): string
    {
        return $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
    }

    public function category(): string
    {
        return $this->metadataPool->getMetadata(CategoryInterface::class)->getLinkField();
    }

    public function isStaged(): bool
    {
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);

        return $metadata->getLinkField() !== $metadata->getIdentifierField();
    }
}
