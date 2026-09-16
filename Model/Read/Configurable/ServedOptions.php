<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Configurable;

/**
 * The configurable option rows this request took from documents, so the option provider can answer without a query.
 */
class ServedOptions
{
    /** @var array<string, array<string, array<int, array<string, mixed>>>> Link field value to attribute id to rows. */
    private array $options = [];

    /**
     * Keyed by link field value only, which is what Magento asks the option provider by.
     *
     * @param array<string, array<int, array<string, mixed>>> $optionsByAttribute
     */
    public function remember(array $optionsByAttribute, int $linkId): void
    {
        if ($linkId > 0 && $optionsByAttribute !== []) {
            $this->options[(string) $linkId] = $optionsByAttribute;
        }
    }

    /**
     * @return array<int, array<string, mixed>>|null Null means nothing was served for this product and attribute.
     */
    public function rows(int $linkId, int $attributeId): ?array
    {
        return $this->options[(string) $linkId][(string) $attributeId] ?? null;
    }
}
