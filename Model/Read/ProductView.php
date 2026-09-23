<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;
use Kingletas\CatalogIndex\Api\Data\ProductViewInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * One product as a page sees it: its document, its price for the shopper's group, and its stock.
 */
class ProductView implements ProductViewInterface
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

    private readonly ConfigurableViewInterface $configurable;

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed>|null $price
     * @param array<string, mixed>|null $stock
     */
    public function __construct(
        private readonly int $id,
        private readonly array $source,
        private readonly ?array $price = null,
        private readonly ?array $stock = null
    ) {
        $this->configurable = new ConfigurableView($source);
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurable(): ConfigurableViewInterface
    {
        return $this->configurable;
    }

    /**
     * @inheritDoc
     */
    public function typeId(): string
    {
        return (string) ($this->source['type_id'] ?? '');
    }

    /**
     * @inheritDoc
     */
    public function attributes(): array
    {
        return $this->map('detail_attributes') + $this->map('listing_attributes') + $this->identity();
    }

    /**
     * @inheritDoc
     */
    public function requestPath(): ?string
    {
        $path = $this->source['request_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * @inheritDoc
     */
    public function mediaGallery(): ?array
    {
        $gallery = $this->source['media_gallery'] ?? null;

        return is_array($gallery) ? $gallery : null;
    }

    /**
     * @inheritDoc
     */
    public function tierPrice(): ?array
    {
        $tiers = $this->source['tier_price'] ?? null;

        return is_array($tiers) ? array_values($tiers) : null;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
     */
    public function categoryIds(): ?array
    {
        $ids = $this->source['category_ids'] ?? null;

        return is_array($ids) ? array_map('intval', $ids) : null;
    }

    /**
     * @inheritDoc
     */
    public function price(): ?array
    {
        return $this->price;
    }

    /**
     * @inheritDoc
     */
    public function isSalable(): ?bool
    {
        return $this->stock === null ? null : (bool) ($this->stock['is_salable'] ?? false);
    }

    /**
     * @inheritDoc
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
