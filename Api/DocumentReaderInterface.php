<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Model\Read\ReadContext;

/**
 * Reads what a storefront page needs from documents.
 */
interface DocumentReaderInterface
{
    /**
     * @param int[] $productIds
     * @return array<int, ProductView> Keyed by product id; products without a document omitted.
     * @throws DocumentStoreException
     */
    public function products(array $productIds, ReadContext $context): array;

    /**
     * @param int[] $categoryIds
     * @return array<int, CategoryView> Keyed by category id; categories without a document omitted.
     * @throws DocumentStoreException
     */
    public function categories(array $categoryIds, ReadContext $context): array;
}
