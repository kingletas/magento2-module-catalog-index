<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Exception;

use RuntimeException;

/**
 * The document store could not be reached or refused a request it should have accepted.
 */
class DocumentStoreException extends RuntimeException
{
}
