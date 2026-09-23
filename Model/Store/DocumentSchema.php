<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Store;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;

/**
 * The shape every document is written in; a document in another shape is read as missing until a rebuild replaces it.
 */
class DocumentSchema
{
    /** Raise this whenever a stored field is renamed, removed or changes meaning. */
    public const int VERSION = 1;

    public const string FIELD = '_schema';

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    public function stamp(array $source): array
    {
        $source[self::FIELD] = self::VERSION;

        return $source;
    }

    public function isCurrent(DocumentInterface $document): bool
    {
        $version = $document->get(self::FIELD);

        return is_int($version) && $version === self::VERSION;
    }
}
