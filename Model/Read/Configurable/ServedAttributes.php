<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read\Configurable;

use Kingletas\CatalogIndex\Model\Read\ConfigurableView;

/**
 * The configurable products this request read from documents, kept until Magento asks one for its attributes.
 */
class ServedAttributes
{
    /** @var array<int, ConfigurableView> */
    private array $views = [];

    /**
     * Keyed by entity id only, because under content staging a row id can equal another product's entity id.
     */
    public function remember(ConfigurableView $view, int $entityId): void
    {
        if ($entityId > 0 && $view->superAttributes() !== []) {
            $this->views[$entityId] = $view;
        }
    }

    public function forProduct(int $entityId): ?ConfigurableView
    {
        return $this->views[$entityId] ?? null;
    }
}
