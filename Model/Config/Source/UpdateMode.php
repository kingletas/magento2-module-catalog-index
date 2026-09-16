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
 * The lanes a refresh can take.
 */
class UpdateMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_QUEUE, 'label' => __('Queue')],
            ['value' => Config::MODE_INLINE, 'label' => __('Inline for small batches')],
            ['value' => Config::MODE_SCHEDULE, 'label' => __('Change log only')],
        ];
    }
}
