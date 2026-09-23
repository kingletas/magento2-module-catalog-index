<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Exception\DocumentStoreException;

/**
 * Creates, swaps and removes the physical indexes behind each alias.
 *
 * @api
 */
interface IndexAdminInterface
{
    /**
     * @param array<string, mixed> $definition Settings and mappings.
     * @throws DocumentStoreException
     */
    public function createIndex(string $index, array $definition): void;

    /**
     * Makes everything written so far visible to counts and searches, which otherwise lag by the refresh interval.
     *
     * @throws DocumentStoreException
     */
    public function refresh(string $index): void;

    /**
     * Points an alias at one index atomically and returns the index it pointed at before.
     *
     * @throws DocumentStoreException
     */
    public function pointAlias(string $alias, string $index): ?string;

    /**
     * @throws DocumentStoreException
     */
    public function resolveAlias(string $alias): ?string;

    /**
     * @throws DocumentStoreException
     */
    public function dropIndex(string $index): void;

    /**
     * @throws DocumentStoreException
     */
    public function count(string $index): int;

    /**
     * @return string[] Physical index names starting with the prefix, newest name last.
     * @throws DocumentStoreException
     */
    public function listIndexes(string $prefix): array;
}
