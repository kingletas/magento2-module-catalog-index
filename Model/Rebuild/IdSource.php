<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Rebuild;

use Generator;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Every id a family holds for one scope, walked in keyset batches so no batch reads past its own end.
 */
class IdSource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeResolver $scopes
    ) {
    }

    /**
     * @return Generator<int, int[]>
     */
    public function batches(IndexFamily $family, int $scopeId, int $size): Generator
    {
        $last = 0;
        $size = max(1, $size);

        do {
            $select = $this->select($family, $scopeId, $last)->limit($size);
            $ids = array_map('intval', $this->resourceConnection->getConnection()->fetchCol($select));
            $fetched = count($ids);

            if ($fetched > 0) {
                $last = (int) end($ids);

                yield $ids;
            }
        } while ($fetched === $size);
    }

    private function select(IndexFamily $family, int $scopeId, int $after): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteId = $family === IndexFamily::Stock || $family === IndexFamily::Price
            ? $scopeId
            : $this->scopes->websiteIdOf($scopeId);

        return match ($family) {
            IndexFamily::Product, IndexFamily::Stock => $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_product_website'), ['product_id'])
                ->where('website_id = ?', $websiteId)
                ->where('product_id > ?', $after)
                ->order('product_id ASC'),
            IndexFamily::Price => $connection->select()
                ->distinct()
                ->from($this->resourceConnection->getTableName('catalog_product_index_price'), ['entity_id'])
                ->where('website_id = ?', $scopeId)
                ->where('entity_id > ?', $after)
                ->order('entity_id ASC'),
            IndexFamily::Category => $connection->select()
                ->from($this->resourceConnection->getTableName('catalog_category_entity'), ['entity_id'])
                ->where(
                    $connection->quoteInto('path LIKE ?', '1/' . $this->scopes->rootCategoryOf($scopeId) . '/%')
                    . ' OR ' . $connection->quoteInto('entity_id = ?', $this->scopes->rootCategoryOf($scopeId))
                )
                ->where('entity_id > ?', $after)
                ->order('entity_id ASC'),
        };
    }
}
