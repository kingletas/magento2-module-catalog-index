<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Magento\Catalog\Model\Product;

/**
 * Contributes one concern's fields to a product document.
 */
interface FieldProviderInterface
{
    /**
     * Loads everything the batch needs, so contribute() makes no round trips.
     *
     * @param array<int, Product> $products Keyed by entity id.
     */
    public function prepareBatch(array $products, BuildContext $context): void;

    public function contribute(Product $product, DocumentDraft $draft, BuildContext $context): void;

    public function resetBatch(): void;

    public function getSortOrder(): int;
}
