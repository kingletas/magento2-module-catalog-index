<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * One product as a page sees it: its document, its price for the shopper's group, and its stock.
 *
 * @api
 */
interface ProductViewInterface
{
    public function getId(): int;

    public function getConfigurable(): ConfigurableViewInterface;

    public function typeId(): string;

    /**
     * @return array<string, mixed> Every attribute value, listing and detail together.
     */
    public function attributes(): array;

    public function requestPath(): ?string;

    /**
     * @return array<string, mixed>|null
     */
    public function mediaGallery(): ?array;

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function tierPrice(): ?array;

    /**
     * @return array{rating_summary: int, reviews_count: int}|null
     */
    public function reviewSummary(): ?array;

    /**
     * Null when the document carries no category list, which is not the same as a product in no category.
     *
     * @return int[]|null
     */
    public function categoryIds(): ?array;

    /**
     * @return array<string, mixed>|null Prices for the shopper's customer group.
     */
    public function price(): ?array;

    public function isSalable(): ?bool;

    /**
     * A product page can be built from the document only when nothing it renders lives outside it.
     */
    public function isDetailServable(): bool;
}
