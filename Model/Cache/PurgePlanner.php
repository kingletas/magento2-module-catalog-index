<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Cache;

use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Kingletas\CatalogIndex\Model\Build\CategoryDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;

/**
 * Decides which cache tags a set of document changes invalidates, and names nothing a shopper would not see change.
 */
class PurgePlanner
{
    private const array VISIBLE_PRODUCT_GROUPS = [
        DocumentDraftInterface::GROUP_LISTING,
        DocumentDraftInterface::GROUP_DETAIL,
    ];

    private const array VISIBLE_STOCK_GROUPS = [
        StockDocumentBuilder::GROUP_SALABLE,
        StockDocumentBuilder::GROUP_LEVEL,
        StockDocumentBuilder::GROUP_VARIANTS,
    ];

    /**
     * @return string[]
     */
    public function forProducts(ChangeSetInterface $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny(self::VISIBLE_PRODUCT_GROUPS)) {
                $tags[] = $this->product($change->getId());
            }

            $categories = $change->isCreated() || $change->isDeleted()
                ? $change->allCategories()
                : $change->movedCategories();

            foreach ($categories as $categoryId) {
                $tags[] = $this->categoryProducts($categoryId);
            }
        }

        return $this->unique($tags);
    }

    /**
     * @return string[]
     */
    public function forPrices(ChangeSetInterface $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            $tags[] = $this->product($change->getId());
        }

        return $this->unique($tags);
    }

    /**
     * A product that became or stopped being buyable can appear on or leave category pages.
     *
     * @param array<int, int[]> $categoriesByProduct
     * @return string[]
     */
    public function forStock(ChangeSetInterface $changes, array $categoriesByProduct): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if (!$change->touchesAny(self::VISIBLE_STOCK_GROUPS)) {
                continue;
            }

            $tags[] = $this->product($change->getId());

            if ($change->touchesAny([StockDocumentBuilder::GROUP_SALABLE])) {
                foreach ($categoriesByProduct[$change->getId()] ?? [] as $categoryId) {
                    $tags[] = $this->categoryProducts($categoryId);
                }
            }
        }

        return $this->unique($tags);
    }

    /**
     * @return int[] Products whose salable flag changed.
     */
    public function salabilityFlips(ChangeSetInterface $changes): array
    {
        $ids = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny([StockDocumentBuilder::GROUP_SALABLE])) {
                $ids[] = $change->getId();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return string[]
     */
    public function forCategories(ChangeSetInterface $changes): array
    {
        $tags = [];

        foreach ($changes->changes() as $change) {
            if ($change->touchesAny([CategoryDocumentBuilder::GROUP_PAGE])) {
                $tags[] = Category::CACHE_TAG . '_' . $change->getId();
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
