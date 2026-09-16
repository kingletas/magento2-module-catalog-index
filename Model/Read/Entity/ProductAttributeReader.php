<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Entity;

use Kingletas\CatalogIndex\Model\Read\DetailDocuments;
use Kingletas\CatalogIndex\Model\Read\ProductHydrator;
use Magento\Framework\EntityManager\Operation\AttributeInterface;

/**
 * Reads a product page's attribute values from its document, and every other product load from the database.
 */
class ProductAttributeReader implements AttributeInterface
{
    public function __construct(
        private readonly AttributeInterface $databaseReader,
        private readonly DetailDocuments $documents,
        private readonly ProductHydrator $hydrator
    ) {
    }

    /**
     * @inheritDoc
     * @param string $entityType
     * @param mixed[] $entityData
     * @param mixed[] $arguments
     * @return mixed[]
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function execute($entityType, $entityData, $arguments = []): array
    {
        $storeId = (int) ($entityData['store_id'] ?? 0);
        $view = empty($entityData['_edit_mode'])
            ? $this->documents->product((int) ($entityData['entity_id'] ?? 0), $storeId)
            : null;

        if ($view === null) {
            return (array) $this->databaseReader->execute($entityType, $entityData, $arguments);
        }

        return $this->hydrator->detailData($view, $entityData, $storeId);
    }
}
