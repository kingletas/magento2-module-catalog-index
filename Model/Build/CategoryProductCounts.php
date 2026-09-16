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
 * How many products each category in a batch holds, counted the way a category page counts them.
 */
class CategoryProductCounts
{
    /** @var array<int, int> Category id to the number of products assigned to it. */
    private array $counts = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * One query for the whole batch, where a page asks a category at a time.
     *
     * @param int[] $categoryIds
     */
    public function prepare(array $categoryIds): void
    {
        $this->counts = [];

        if ($categoryIds === []) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_category_product'),
                ['category_id', 'products' => 'COUNT(product_id)']
            )
            ->where('category_id IN (?)', $categoryIds)
            ->group('category_id');

        foreach ($connection->fetchPairs($select) as $categoryId => $count) {
            $this->counts[(int) $categoryId] = (int) $count;
        }
    }

    /**
     * A category with no assignment counts zero, which is what Magento's own query returns for it.
     */
    public function productCount(int $categoryId): int
    {
        return $this->counts[$categoryId] ?? 0;
    }
}
