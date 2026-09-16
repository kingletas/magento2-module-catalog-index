<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * One product as a page sees it: its document, its price for the shopper's group, and its stock.
 */
class ProductView
{
    private const array SERVABLE_DETAIL_TYPES = [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL, Configurable::TYPE_CODE];

    private const array IDENTITY_FIELDS = [
        'entity_id',
        'sku',
        'type_id',
        'visibility',
        'required_options',
        'attribute_set_id',
        'has_options',
    ];

    public readonly ConfigurableView $configurable;

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed>|null $price
     * @param array<string, mixed>|null $stock
     */
    public function __construct(
        public readonly int $id,
        private readonly array $source,
        private readonly ?array $price = null,
        private readonly ?array $stock = null
    ) {
        $this->configurable = new ConfigurableView($source);
    }

    public function typeId(): string
    {
        return (string) ($this->source['type_id'] ?? '');
    }

    /**
     * @return array<string, mixed> Every attribute value, listing and detail together.
     */
    public function attributes(): array
    {
        return $this->map('detail_attributes') + $this->map('listing_attributes') + $this->identity();
    }

    public function requestPath(): ?string
    {
        $path = $this->source['request_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mediaGallery(): ?array
    {
        $gallery = $this->source['media_gallery'] ?? null;

        return is_array($gallery) ? $gallery : null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function tierPrice(): ?array
    {
        $tiers = $this->source['tier_price'] ?? null;

        return is_array($tiers) ? array_values($tiers) : null;
    }

    /**
     * @return array{rating_summary: int, reviews_count: int}|null
     */
    public function reviewSummary(): ?array
    {
        if (!array_key_exists('rating_summary', $this->source)) {
            return null;
        }

        return [
            'rating_summary' => (int) $this->source['rating_summary'],
            'reviews_count' => (int) ($this->source['reviews_count'] ?? 0),
        ];
    }

    /**
     * Null when the document carries no category list, which is not the same as a product in no category.
     *
     * @return int[]|null
     */
    public function categoryIds(): ?array
    {
        $ids = $this->source['category_ids'] ?? null;

        return is_array($ids) ? array_map('intval', $ids) : null;
    }

    /**
     * @return array<string, mixed>|null Prices for the shopper's customer group.
     */
    public function price(): ?array
    {
        return $this->price;
    }

    public function isSalable(): ?bool
    {
        return $this->stock === null ? null : (bool) ($this->stock['is_salable'] ?? false);
    }

    /**
     * A product page can be built from the document only when nothing it renders lives outside it.
     */
    public function isDetailServable(): bool
    {
        return in_array($this->typeId(), self::SERVABLE_DETAIL_TYPES, true)
            && ($this->source['has_custom_options'] ?? true) === false
            && $this->stock !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(): array
    {
        $identity = [];

        foreach (self::IDENTITY_FIELDS as $field) {
            if (array_key_exists($field, $this->source)) {
                $identity[$field] = $this->source[$field];
            }
        }

        return $identity;
    }

    /**
     * @return array<string, mixed>
     */
    private function map(string $field): array
    {
        $map = $this->source[$field] ?? [];

        return is_array($map) ? $map : [];
    }
}
