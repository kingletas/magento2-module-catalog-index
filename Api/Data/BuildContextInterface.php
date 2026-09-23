<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

use DateTimeImmutable;

/**
 * The scope and moment one batch of documents is built for.
 *
 * @api
 */
interface BuildContextInterface
{
    public function getStoreId(): int;

    public function getWebsiteId(): int;

    /**
     * The version every document in the batch is written with.
     */
    public function getVersion(): int;

    public function getNow(): DateTimeImmutable;

    /**
     * The store view's configured timezone name.
     */
    public function getTimezone(): string;
}
