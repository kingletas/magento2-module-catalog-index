<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Entity;

use Kingletas\CatalogIndex\Model\Read\CategoryHydrator;
use Kingletas\CatalogIndex\Model\Read\DetailDocuments;
use Magento\Framework\EntityManager\Operation\AttributeInterface;

/**
 * Reads a category page's attribute values from its document, and every other category load from the database.
 */
class CategoryAttributeReader implements AttributeInterface
{
    public function __construct(
        private readonly AttributeInterface $databaseReader,
        private readonly DetailDocuments $documents,
        private readonly CategoryHydrator $hydrator
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
        $view = $this->documents->category(
            (int) ($entityData['entity_id'] ?? 0),
            (int) ($entityData['store_id'] ?? 0)
        );

        if ($view === null) {
            return (array) $this->databaseReader->execute($entityType, $entityData, $arguments);
        }

        return $this->hydrator->detailData($view, $entityData);
    }
}
