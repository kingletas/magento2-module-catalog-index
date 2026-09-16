<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Magento\Framework\App\ResourceConnection;

/**
 * Widens a set of changed products to the composite products whose documents embed them.
 */
class AffectedProductResolver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LinkField $linkField
    ) {
    }

    /**
     * @param int[] $productIds
     * @return int[] The ids and every parent of them, sorted.
     */
    public function withParents(array $productIds): array
    {
        $productIds = $this->clean($productIds);

        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $parents = $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from(['r' => $this->resourceConnection->getTableName('catalog_product_relation')], [])
                ->join(
                    ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    sprintf('e.%s = r.parent_id', $this->linkField->product()),
                    ['entity_id']
                )
                ->where('r.child_id IN (?)', $productIds)
        );

        return $this->clean(array_merge($productIds, $parents));
    }

    /**
     * @param int[] $parentIds
     * @return array<int, int[]> Parent id to its child ids.
     */
    public function childrenOf(array $parentIds): array
    {
        $parentIds = $this->clean($parentIds);

        if ($parentIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['r' => $this->resourceConnection->getTableName('catalog_product_relation')], ['child_id'])
                ->join(
                    ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
                    sprintf('e.%s = r.parent_id', $this->linkField->product()),
                    ['parent_id' => 'entity_id']
                )
                ->where('e.entity_id IN (?)', $parentIds)
        );
        $children = [];

        foreach ($rows as $row) {
            $children[(int) $row['parent_id']][] = (int) $row['child_id'];
        }

        return $children;
    }

    /**
     * @param int[] $productIds
     * @return array<int, int[]> Product id to the categories it is assigned to.
     */
    public function categoriesOf(array $productIds): array
    {
        $productIds = $this->clean($productIds);

        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName('catalog_category_product'),
                    ['product_id', 'category_id']
                )
                ->where('product_id IN (?)', $productIds)
        );
        $categories = [];

        foreach ($rows as $row) {
            $categories[(int) $row['product_id']][] = (int) $row['category_id'];
        }

        return $categories;
    }

    /**
     * @param array<int|string> $ids
     * @return int[]
     */
    private function clean(array $ids): array
    {
        $ids = array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0);
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
