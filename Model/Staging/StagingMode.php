<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Staging;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Config;
use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Whether scheduled content updates are watched, from the setting and what is actually installed.
 */
class StagingMode
{
    public function __construct(
        private readonly Config $config,
        private readonly ModuleManager $moduleManager,
        private readonly LinkField $linkField
    ) {
    }

    public function isActive(): bool
    {
        return match ($this->config->getStagingMode()) {
            Config::STAGING_DISABLED => false,
            Config::STAGING_ENABLED => $this->linkField->isStaged(),
            default => $this->moduleManager->isEnabled('Magento_Staging') && $this->linkField->isStaged(),
        };
    }
}
