<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * One category as a page sees it.
 *
 * @api
 */
interface CategoryViewInterface
{
    public function getId(): int;

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /**
     * Null when the document carries no count, which is not the same as a category with no products.
     */
    public function productCount(): ?int;

    public function requestPath(): ?string;
}
