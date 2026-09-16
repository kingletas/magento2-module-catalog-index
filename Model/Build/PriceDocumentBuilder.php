<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use Magento\Framework\App\ResourceConnection;

/**
 * Builds one price document per product and website, holding every customer group's indexed prices.
 */
class PriceDocumentBuilder
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FingerprintCalculator $fingerprints
    ) {
    }

    /**
     * @param int[] $productIds
     */
    public function build(array $productIds, int $websiteId, int $version): BuildBatch
    {
        if ($productIds === []) {
            return new BuildBatch($version);
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_product_index_price'),
                    ['entity_id', 'customer_group_id', 'price', 'final_price', 'min_price', 'max_price', 'tier_price']
                )
                ->where('website_id = ?', $websiteId)
                ->where('entity_id IN (?)', $productIds)
                ->order(['entity_id ASC', 'customer_group_id ASC'])
        );
        $groups = [];

        foreach ($rows as $row) {
            $groups[(int) $row['entity_id']][(string) (int) $row['customer_group_id']] = [
                'price' => $this->money($row['price']),
                'final_price' => $this->money($row['final_price']),
                'min_price' => $this->money($row['min_price']),
                'max_price' => $this->money($row['max_price']),
                'tier_price' => $this->money($row['tier_price']),
            ];
        }

        $documents = [];

        foreach ($groups as $productId => $byGroup) {
            $draft = new DocumentDraft($productId, $websiteId);
            $draft->set('groups', $byGroup, DocumentDraft::GROUP_LISTING);
            $documents[] = $this->fingerprints->document($draft, $version);
        }

        $removed = array_values(array_diff(array_map('intval', $productIds), array_keys($groups)));

        return new BuildBatch($version, $documents, $removed);
    }

    private function money(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 4);
    }
}
