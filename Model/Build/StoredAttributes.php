<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

/**
 * Which product data keys a document stores as attribute values, shared by building and reading so they agree.
 */
class StoredAttributes
{
    /**
     * @param string[] $neverStored Keys a product carries that are not attribute values.
     */
    public function __construct(
        private readonly array $neverStored = []
    ) {
    }

    public function isStored(string $code): bool
    {
        return $code !== '' && $code[0] !== '_' && !in_array($code, $this->neverStored, true);
    }
}
