<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

/**
 * The parts of a document only a configurable product has: its super attributes and its option rows.
 */
class ConfigurableView
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        private readonly array $source
    ) {
    }

    /**
     * The option rows Magento's own query would return, keyed by super attribute id.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function options(): array
    {
        $options = $this->source['configurable_options'] ?? [];

        return is_array($options) ? array_filter($options, 'is_array') : [];
    }

    /**
     * The super attribute rows, in the position order Magento's own collection would return them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function superAttributes(): array
    {
        $attributes = $this->source['super_attributes'] ?? [];

        return is_array($attributes) ? array_values(array_filter($attributes, 'is_array')) : [];
    }
}
