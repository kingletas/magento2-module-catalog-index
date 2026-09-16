<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedAttributes;
use Kingletas\CatalogIndex\Model\Read\Configurable\ServedOptions;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Fills Magento products from documents, so every template and plugin keeps working with the objects it expects.
 */
class ProductHydrator
{
    public function __construct(
        private readonly Config $config,
        private readonly ServedOptions $servedOptions,
        private readonly LinkField $linkField,
        private readonly ServedAttributes $servedAttributes
    ) {
    }

    /**
     * Adds what the collection did not load itself, leaving its own columns such as indexed prices as they are.
     */
    public function fillListingItem(Product $item, ProductView $view, int $storeId): void
    {
        foreach ($this->values($view) as $key => $value) {
            if (!$item->hasData($key)) {
                $item->setData($key, $value);
            }
        }

        $this->rememberOptions($view, (int) $item->getData($this->linkField->product()), $storeId);
        $this->rememberAttributes($view, $storeId);
    }

    /**
     * Values a product page load takes from the document, for keys its own entity row did not already supply.
     *
     * @param array<string, mixed> $entityData
     * @return array<string, mixed>
     */
    public function detailData(ProductView $view, array $entityData, int $storeId): array
    {
        $values = $this->values($view);

        if ($view->tierPrice() !== null) {
            $values['tier_price'] = $view->tierPrice();
        }

        $this->rememberOptions($view, (int) ($entityData[$this->linkField->product()] ?? 0), $storeId);
        $this->rememberAttributes($view, $storeId);

        return $entityData + $values;
    }

    /**
     * Kept for when Magento asks, because most surfaces never do and building the collection costs a query each.
     */
    private function rememberAttributes(ProductView $view, int $storeId): void
    {
        if ($view->typeId() !== Configurable::TYPE_CODE
            || !$this->config->isConfigurableAttributesEnabled($storeId)
        ) {
            return;
        }

        $this->servedAttributes->remember($view->configurable, $view->id);
    }

    /**
     * Magento asks its option provider by link field, which is the row id where content staging is installed.
     */
    private function rememberOptions(ProductView $view, int $linkId, int $storeId): void
    {
        if (!$this->config->isConfigurableOptionsEnabled($storeId)) {
            return;
        }

        $this->servedOptions->remember($view->configurable->options(), $linkId);
    }

    /**
     * @return array<string, mixed>
     */
    private function values(ProductView $view): array
    {
        $values = $view->attributes();
        $values['entity_id'] = $view->id;

        if ($view->requestPath() !== null) {
            $values['request_path'] = $view->requestPath();
        }

        if ($view->mediaGallery() !== null) {
            $values['media_gallery'] = $view->mediaGallery();
        }

        // Magento asks the database for a product's categories one product at a time when this is not set.
        if ($view->categoryIds() !== null) {
            $values['category_ids'] = $view->categoryIds();
        }

        if ($view->reviewSummary() !== null) {
            $values += $view->reviewSummary();
        }

        // Only is_salable is set, so isSalable() still runs and dispatches the events catalog permissions listen to.
        if ($view->isSalable() !== null) {
            $values['is_salable'] = (int) $view->isSalable();
        }

        return $values;
    }
}
