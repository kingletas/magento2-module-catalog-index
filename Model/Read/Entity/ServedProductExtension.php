<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Entity;

use Kingletas\CatalogIndex\Model\Read\DetailDocuments;
use Magento\Framework\DataObject;
use Magento\Framework\EntityManager\Operation\ExtensionInterface;

/**
 * Skips one of Magento's product read extensions when the product page was already filled from its document.
 */
class ServedProductExtension implements ExtensionInterface
{
    /**
     * @param array<string, mixed> $servedData Data a served product gets instead, such as no custom options.
     */
    public function __construct(
        private readonly ExtensionInterface $databaseReader,
        private readonly DetailDocuments $documents,
        private readonly array $servedData = []
    ) {
    }

    /**
     * @inheritDoc
     * @param object $entity
     * @param mixed[] $arguments
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function execute($entity, $arguments = [])
    {
        if (!$entity instanceof DataObject || !$this->isServed($entity)) {
            return $this->databaseReader->execute($entity, $arguments);
        }

        foreach ($this->servedData as $key => $value) {
            $entity->setData($key, $value);
        }

        return $entity;
    }

    private function isServed(DataObject $entity): bool
    {
        return empty($entity->getData('_edit_mode'))
            && $this->documents->product(
                (int) $entity->getData('entity_id'),
                (int) $entity->getData('store_id')
            ) !== null;
    }
}
