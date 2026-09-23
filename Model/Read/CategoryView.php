<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\CategoryViewInterface;

/**
 * One category as a page sees it.
 */
class CategoryView implements CategoryViewInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        private readonly int $id,
        private readonly array $source
    ) {
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
    public function attributes(): array
    {
        $attributes = $this->source['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * @inheritDoc
     */
    public function productCount(): ?int
    {
        $count = $this->source['product_count'] ?? null;

        return is_numeric($count) ? (int) $count : null;
    }

    /**
     * @inheritDoc
     */
    public function requestPath(): ?string
    {
        $path = $this->source['request_path'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }
}
