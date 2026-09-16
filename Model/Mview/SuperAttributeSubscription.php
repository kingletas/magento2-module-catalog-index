<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Mview;

/**
 * Logs the configurable whose super attributes changed; the table keys them by the product's link field.
 */
class SuperAttributeSubscription extends LinkFieldSubscription
{
    /**
     * @inheritDoc
     */
    protected function entityIdLookup(string $row): ?string
    {
        $metadata = $this->metadata();

        if ($metadata->getLinkField() === $metadata->getIdentifierField()) {
            return null;
        }

        return $this->entityIdByLinkValue($row . '.' . $this->connection->quoteIdentifier('product_id'));
    }
}
