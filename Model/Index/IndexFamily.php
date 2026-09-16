<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Index;

/**
 * The four kinds of document, each with its own index, change log and refresh lane.
 */
enum IndexFamily: string
{
    case Product = 'product';
    case Price = 'price';
    case Stock = 'stock';
    case Category = 'category';

    /**
     * Price and stock differ per website; products and categories per store view.
     */
    public function isWebsiteScoped(): bool
    {
        return $this === self::Price || $this === self::Stock;
    }

    /**
     * Price and stock ride the priority lane because a shopper acts on them.
     */
    public function isPriority(): bool
    {
        return $this->isWebsiteScoped();
    }
}
