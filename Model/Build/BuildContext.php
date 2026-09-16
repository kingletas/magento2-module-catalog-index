<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;

/**
 * The scope and moment one batch of documents is built for.
 */
class BuildContext
{
    public function __construct(
        public readonly int $storeId,
        public readonly int $websiteId,
        public readonly int $version,
        public readonly DateTimeImmutable $now,
        public readonly string $timezone = 'UTC'
    ) {
    }
}
