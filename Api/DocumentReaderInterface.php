<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Api\Data\CategoryViewInterface;
use Kingletas\CatalogIndex\Api\Data\ProductViewInterface;
use Kingletas\CatalogIndex\Api\Data\ReadContextInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;

/**
 * Reads what a storefront page needs from documents.
 *
 * @api
 */
interface DocumentReaderInterface
{
    /**
     * @param int[] $productIds
     * @return array<int, ProductViewInterface> Keyed by product id; products without a document omitted.
     * @throws DocumentStoreException
     */
    public function products(array $productIds, ReadContextInterface $context): array;

    /**
     * @param int[] $categoryIds
     * @return array<int, CategoryViewInterface> Keyed by category id; categories without a document omitted.
     * @throws DocumentStoreException
     */
    public function categories(array $categoryIds, ReadContextInterface $context): array;
}
