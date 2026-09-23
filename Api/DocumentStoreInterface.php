<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\Data\WriteResultInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;

/**
 * Reads and writes documents, addressed by index or alias name.
 *
 * @api
 */
interface DocumentStoreInterface
{
    /**
     * Writes documents, refusing any whose version is older than the stored one.
     *
     * @param DocumentInterface[] $documents
     * @throws DocumentStoreException
     */
    public function write(string $index, array $documents): WriteResultInterface;

    /**
     * @param string[] $ids
     * @throws DocumentStoreException
     */
    public function delete(string $index, array $ids, int $version): WriteResultInterface;

    /**
     * Reads documents from several indexes in one round trip.
     *
     * @param array<string, string[]> $idsByIndex
     * @param string[] $fields Source fields to return; empty returns every field.
     * @return array<string, array<string, DocumentInterface>> Index name, then document id; misses omitted.
     * @throws DocumentStoreException
     */
    public function fetch(array $idsByIndex, array $fields = []): array;
}
