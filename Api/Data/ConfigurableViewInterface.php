<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * The parts of a document only a configurable product has: its super attributes and its option rows.
 *
 * @api
 */
interface ConfigurableViewInterface
{
    /**
     * The option rows Magento's own query would return, keyed by super attribute id.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function options(): array;

    /**
     * The super attribute rows, in the position order Magento's own collection would return them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function superAttributes(): array;
}
