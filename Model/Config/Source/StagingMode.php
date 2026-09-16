<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Config\Source;

use Kingletas\CatalogIndex\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Whether scheduled content updates are watched.
 */
class StagingMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::STAGING_AUTO, 'label' => __('Automatic')],
            ['value' => Config::STAGING_ENABLED, 'label' => __('Always')],
            ['value' => Config::STAGING_DISABLED, 'label' => __('Never')],
        ];
    }
}
