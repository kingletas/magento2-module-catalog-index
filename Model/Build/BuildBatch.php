<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\Data\DocumentInterface;

/**
 * The documents a batch produced, the ids it found nothing to publish for, and when to look again.
 */
class BuildBatch
{
    /**
     * @param DocumentInterface[] $documents
     * @param int[] $removedIds
     * @param array<int, array<int, DateTimeImmutable>> $refreshMoments Entity id to the moments it changes.
     */
    public function __construct(
        public readonly int $version,
        public readonly array $documents = [],
        public readonly array $removedIds = [],
        public readonly array $refreshMoments = []
    ) {
    }

    /**
     * @return string[]
     */
    public function documentIds(): array
    {
        return array_map(static fn (DocumentInterface $document): string => $document->getId(), $this->documents);
    }
}
