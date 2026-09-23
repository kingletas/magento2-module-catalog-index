<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;

/**
 * The parts of a document only a configurable product has: its super attributes and its option rows.
 */
class ConfigurableView implements ConfigurableViewInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        private readonly array $source
    ) {
    }

    /**
     * @inheritDoc
     */
    public function options(): array
    {
        $options = $this->source['configurable_options'] ?? [];

        return is_array($options) ? array_filter($options, 'is_array') : [];
    }

    /**
     * @inheritDoc
     */
    public function superAttributes(): array
    {
        $attributes = $this->source['super_attributes'] ?? [];

        return is_array($attributes) ? array_values(array_filter($attributes, 'is_array')) : [];
    }
}
