<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;

/**
 * Rebuilds one family of documents from the database.
 *
 * @api
 */
interface RefresherInterface
{
    public function family(): IndexFamily;

    /**
     * Rebuilds the documents in every scope's live index and purges what changed.
     *
     * @param int[] $ids
     */
    public function refresh(array $ids): void;

    /**
     * Rebuilds documents into one index, comparing against another, without purging.
     *
     * @param int[] $ids
     */
    public function refreshInto(array $ids, int $scopeId, string $writeIndex, string $compareIndex): ChangeSetInterface;
}
