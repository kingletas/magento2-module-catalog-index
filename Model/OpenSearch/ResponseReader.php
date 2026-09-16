<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Store\WriteResult;

/**
 * Reads bulk and multi-get answers into results, one document at a time.
 */
class ResponseReader
{
    private const int CONFLICT = 409;
    private const int NOT_FOUND = 404;

    /**
     * A version conflict means a newer document is already stored, which is the outcome versioning exists to produce.
     *
     * @param array<mixed> $body
     */
    public function writeResult(array $body, string $operation): WriteResult
    {
        $written = [];
        $stale = [];
        $failed = [];

        foreach ((array) ($body['items'] ?? []) as $item) {
            $result = is_array($item) && is_array($item[$operation] ?? null) ? $item[$operation] : [];
            $id = (string) ($result['_id'] ?? '');
            $status = (int) ($result['status'] ?? 0);

            if ($status === self::CONFLICT) {
                $stale[] = $id;

                continue;
            }

            if ($this->isAccepted($status, $operation)) {
                $written[] = $id;

                continue;
            }

            $failed[$id] = $this->reason($result, $status);
        }

        return new WriteResult($written, $stale, $failed);
    }

    /**
     * Answers come in request order under the physical index name, so each is filed under the name asked for.
     *
     * @param array<mixed> $docs
     * @param string[] $requested Index or alias name of each requested document, in request order.
     * @return array<string, array<string, DocumentInterface>>
     */
    public function documents(array $docs, array $requested = []): array
    {
        $found = [];

        foreach (array_values($docs) as $position => $doc) {
            if (!is_array($doc) || ($doc['found'] ?? false) !== true) {
                continue;
            }

            $index = $requested[$position] ?? (string) $doc['_index'];
            $found[$index][(string) $doc['_id']] = new Document(
                (string) $doc['_id'],
                (int) ($doc['_version'] ?? 0),
                is_array($doc['_source'] ?? null) ? $doc['_source'] : []
            );
        }

        return $found;
    }

    private function isAccepted(int $status, string $operation): bool
    {
        return ($status >= 200 && $status < 300) || ($operation === 'delete' && $status === self::NOT_FOUND);
    }

    /**
     * @param array<mixed> $result
     */
    private function reason(array $result, int $status): string
    {
        $error = is_array($result['error'] ?? null) ? $result['error'] : [];

        return (string) ($error['reason'] ?? $error['type'] ?? 'HTTP ' . $status);
    }
}
