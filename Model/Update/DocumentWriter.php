<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Psr\Log\LoggerInterface;

/**
 * Writes a batch and reports which documents actually changed, compared field group by field group.
 */
class DocumentWriter
{
    private const string CATEGORIES = 'category_ids';

    public function __construct(
        private readonly DocumentStoreInterface $store,
        private readonly LoggerInterface $logger
    ) {
    }

    public function replace(string $writeIndex, string $compareIndex, BuildBatch $batch, string $scope = ''): ChangeSet
    {
        $changes = new ChangeSet();
        $ids = array_merge($batch->documentIds(), array_map('strval', $batch->removedIds));

        if ($ids === []) {
            return $changes;
        }

        $before = $this->store->fetch(
            [$compareIndex => $ids],
            [DocumentInterface::FINGERPRINTS, self::CATEGORIES]
        )[$compareIndex] ?? [];
        $result = $this->store->write($writeIndex, $batch->documents);
        $accepted = array_fill_keys($result->written, true);

        foreach ($batch->documents as $document) {
            if (isset($accepted[$document->getId()])) {
                $changes->add($this->compare($before[$document->getId()] ?? null, $document), $scope);
            }
        }

        $gone = array_values(array_filter(
            array_map('strval', $batch->removedIds),
            static fn (string $id): bool => isset($before[$id])
        ));

        if ($gone !== [] && $writeIndex === $compareIndex) {
            $deleted = $this->store->delete($writeIndex, $gone, $batch->version);
            $result = $result->merge($deleted);

            foreach ($deleted->written as $id) {
                $categories = $this->categories($before[$id] ?? null);
                $changes->add(new Change((int) $id, false, true, [], $categories), $scope);
            }
        }

        $changes->record(count($result->written), count($result->stale), $result->failed);
        $this->reportFailures($writeIndex, $result->failed);

        return $changes;
    }

    private function compare(?DocumentInterface $before, DocumentInterface $after): Change
    {
        $afterCategories = $this->categories($after);

        if ($before === null) {
            return new Change((int) $after->getId(), true, false, [], [], $afterCategories);
        }

        $groups = array_keys(array_merge(
            (array) $before->get(DocumentInterface::FINGERPRINTS, []),
            (array) $after->get(DocumentInterface::FINGERPRINTS, [])
        ));
        $changed = array_values(array_filter(
            $groups,
            static fn (string $group): bool => $before->getFingerprint($group) !== $after->getFingerprint($group)
        ));

        return new Change((int) $after->getId(), false, false, $changed, $this->categories($before), $afterCategories);
    }

    /**
     * @return int[]
     */
    private function categories(?DocumentInterface $document): array
    {
        $categories = $document?->get(self::CATEGORIES, []);

        return is_array($categories) ? array_map('intval', $categories) : [];
    }

    /**
     * @param array<string, string> $failed
     */
    private function reportFailures(string $index, array $failed): void
    {
        if ($failed === []) {
            return;
        }

        $this->logger->error(sprintf(
            'Catalog index: %d documents were refused by %s, first %s: %s',
            count($failed),
            $index,
            (string) array_key_first($failed),
            (string) reset($failed)
        ));
    }
}
