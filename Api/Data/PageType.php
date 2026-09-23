<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * The storefront surfaces that can read documents, each switched on separately.
 *
 * @api
 */
enum PageType: string
{
    case CategoryListing = 'category_listing';
    case SearchListing = 'search_listing';
    case ProductView = 'product_view';
    case CategoryView = 'category_view';
    case CategoryTree = 'category_tree';
    case LinkedProducts = 'linked_products';
    case Widget = 'widget';
    case GraphQl = 'graphql';
}
