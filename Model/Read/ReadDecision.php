<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

/**
 * Whether a page may read documents right now.
 */
enum ReadDecision
{
    case Allow;
    case Disabled;
    case BreakerOpen;
}
