<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

/**
 * One category as a page sees it.
 */
class CategoryView
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        public readonly int $id,
        private readonly array $source
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $attributes = $this->source['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * Null when the document carries no count, which is not the same as a category with no products.
     */
    public function productCount(): ?int
    {
        $count = $this->source['product_count'] ?? null;

        return is_numeric($count) ? (int) $count : null;
    }

    public function requestPath(): ?string
    {
        $path = $this->source['request_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }
}
