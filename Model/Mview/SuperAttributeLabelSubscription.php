<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Mview;

/**
 * Logs the configurable whose super attribute label changed, found through the super attribute the label belongs to.
 */
class SuperAttributeLabelSubscription extends LinkFieldSubscription
{
    /**
     * A label row names only its super attribute, so this lookup is needed on every edition.
     *
     * @inheritDoc
     */
    protected function entityIdLookup(string $row): ?string
    {
        $metadata = $this->metadata();
        $superAttributeId = $this->connection->quoteIdentifier('product_super_attribute_id');

        return sprintf(
            'SELECT entity.%s FROM %s AS entity INNER JOIN %s AS super_attribute ON entity.%s = super_attribute.%s '
                . 'WHERE super_attribute.%s = %s.%s LIMIT 1',
            $this->connection->quoteIdentifier($metadata->getIdentifierField()),
            $this->table($metadata->getEntityTable()),
            $this->table('catalog_product_super_attribute'),
            $this->connection->quoteIdentifier($metadata->getLinkField()),
            $this->connection->quoteIdentifier('product_id'),
            $superAttributeId,
            $row,
            $superAttributeId
        );
    }
}
