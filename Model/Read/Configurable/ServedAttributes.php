<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Configurable;

use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;

/**
 * The configurable products this request read from documents, kept until Magento asks one for its attributes.
 */
class ServedAttributes
{
    /** @var array<int, ConfigurableViewInterface> */
    private array $views = [];

    /** @var array<int, true> Entity ids Magento has already asked this module about. */
    private array $asked = [];

    /**
     * Keyed by entity id only, because under content staging a row id can equal another product's entity id.
     */
    public function remember(ConfigurableViewInterface $view, int $entityId): void
    {
        if ($entityId > 0 && $view->superAttributes() !== []) {
            $this->views[$entityId] = $view;
        }
    }

    /**
     * Also notes the ask, so an answer found on the product later is never mistaken for a pre-emption.
     */
    public function forProduct(int $entityId): ?ConfigurableViewInterface
    {
        $this->asked[$entityId] = true;

        return $this->views[$entityId] ?? null;
    }

    /**
     * True once for a product that had a view and arrived already answered by a plugin sorted before this one.
     */
    public function notePreempted(int $entityId): bool
    {
        if (isset($this->asked[$entityId]) || !isset($this->views[$entityId])) {
            return false;
        }

        $this->asked[$entityId] = true;

        return true;
    }
}
