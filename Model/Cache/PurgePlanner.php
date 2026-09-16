<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

use Kingletas\CatalogIndex\Model\Build\CategoryDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;

/**
 * Decides which cache tags a set of document changes invalidates, and names nothing a shopper would not see change.
 */
class PurgePlanner
{
    private const array VISIBLE_PRODUCT_GROUPS = [DocumentDraft::GROUP_LISTING, DocumentDraft::GROUP_DETAIL];

    private const array VISIBLE_STOCK_GROUPS = [
        StockDocumentBuilder::GROUP_SALABLE,
        StockDocumentBuilder::GROUP_LEVEL,
        StockDocumentBuilder::GROUP_VARIANTS,
    ];

    /**
     * @return string[]
     */
    public function forProducts(ChangeSet $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny(self::VISIBLE_PRODUCT_GROUPS)) {
                $tags[] = $this->product($change->id);
            }

            $categories = $change->created || $change->deleted ? $change->allCategories() : $change->movedCategories();

            foreach ($categories as $categoryId) {
                $tags[] = $this->categoryProducts($categoryId);
            }
        }

        return $this->unique($tags);
    }

    /**
     * @return string[]
     */
    public function forPrices(ChangeSet $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            $tags[] = $this->product($change->id);
        }

        return $this->unique($tags);
    }

    /**
     * A product that became or stopped being buyable can appear on or leave category pages.
     *
     * @param array<int, int[]> $categoriesByProduct
     * @return string[]
     */
    public function forStock(ChangeSet $changes, array $categoriesByProduct): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if (!$change->touchesAny(self::VISIBLE_STOCK_GROUPS)) {
                continue;
            }

            $tags[] = $this->product($change->id);

            if ($change->touchesAny([StockDocumentBuilder::GROUP_SALABLE])) {
                foreach ($categoriesByProduct[$change->id] ?? [] as $categoryId) {
                    $tags[] = $this->categoryProducts($categoryId);
                }
            }
        }

        return $this->unique($tags);
    }

    /**
     * @return int[] Products whose salable flag changed.
     */
    public function salabilityFlips(ChangeSet $changes): array
    {
        $ids = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny([StockDocumentBuilder::GROUP_SALABLE])) {
                $ids[] = $change->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return string[]
     */
    public function forCategories(ChangeSet $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny([CategoryDocumentBuilder::GROUP_PAGE])) {
                $tags[] = Category::CACHE_TAG . '_' . $change->id;
            }
        }

        return $this->unique($tags);
    }

    private function product(int $productId): string
    {
        return Product::CACHE_TAG . '_' . $productId;
    }

    private function categoryProducts(int $categoryId): string
    {
        return Product::CACHE_PRODUCT_CATEGORY_TAG . '_' . $categoryId;
    }

    /**
     * @param string[] $tags
     * @return string[]
     */
    private function unique(array $tags): array
    {
        $tags = array_values(array_unique($tags));
        sort($tags);

        return $tags;
    }
}
