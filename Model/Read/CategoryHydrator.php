<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Magento\Catalog\Model\Category;

/**
 * Supplies a category page's attribute values from its document.
 */
class CategoryHydrator
{
    /**
     * Values a category page load takes from the document, for keys its own entity row did not already supply.
     *
     * @param array<string, mixed> $entityData
     * @return array<string, mixed>
     */
    public function detailData(CategoryView $view, array $entityData): array
    {
        return $entityData + $this->values($view);
    }

    /**
     * Adds what the collection did not load itself, so a menu, a breadcrumb and a filter cost no query each.
     */
    public function fillListItem(Category $item, CategoryView $view): void
    {
        foreach ($this->values($view) as $key => $value) {
            if (!$item->hasData($key)) {
                $item->setData($key, $value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function values(CategoryView $view): array
    {
        $values = $view->attributes();

        if ($view->requestPath() !== null) {
            $values['request_path'] = $view->requestPath();
        }

        // Magento counts a category's products one category at a time when this is not set.
        if ($view->productCount() !== null) {
            $values['product_count'] = $view->productCount();
        }

        return $values;
    }
}
