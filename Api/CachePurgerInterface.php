<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

/**
 * Removes cached pages and blocks carrying any of the given tags, and never flushes everything.
 *
 * @api
 */
interface CachePurgerInterface
{
    /**
     * @param string[] $tags
     */
    public function purge(array $tags): void;
}
