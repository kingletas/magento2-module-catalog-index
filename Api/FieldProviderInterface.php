<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Magento\Catalog\Model\Product;

/**
 * Contributes one concern's fields to a product document.
 *
 * @api
 */
interface FieldProviderInterface
{
    /**
     * Loads everything the batch needs, so contribute() makes no round trips.
     *
     * @param array<int, Product> $products Keyed by entity id.
     */
    public function prepareBatch(array $products, BuildContextInterface $context): void;

    public function contribute(Product $product, DocumentDraftInterface $draft, BuildContextInterface $context): void;

    public function resetBatch(): void;

    public function getSortOrder(): int;
}
