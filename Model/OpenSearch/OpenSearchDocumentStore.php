<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\Data\WriteResultInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Model\Store\WriteResult;
use OpenSearch\Client;
use stdClass;

/**
 * Reads and writes documents in OpenSearch, with external versions so an older write never replaces a newer one.
 */
class OpenSearchDocumentStore implements DocumentStoreInterface
{
    private const string VALID_NAME = '/^[a-z0-9][a-z0-9_\-]*$/';

    public function __construct(
        private readonly ClientGateway $gateway,
        private readonly ResponseReader $responses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function write(string $index, array $documents): WriteResultInterface
    {
        $this->assertName($index);

        if ($documents === []) {
            return new WriteResult();
        }

        $body = [];

        foreach ($documents as $document) {
            $body[] = ['index' => $this->meta($index, $document->getId(), $document->getVersion())];
            $body[] = $document->getSource() === [] ? new stdClass() : $document->getSource();
        }

        return $this->bulk($body, 'index');
    }

    /**
     * @inheritDoc
     */
    public function delete(string $index, array $ids, int $version): WriteResultInterface
    {
        $this->assertName($index);

        if ($ids === []) {
            return new WriteResult();
        }

        $body = [];

        foreach ($ids as $id) {
            $body[] = ['delete' => $this->meta($index, (string) $id, $version)];
        }

        return $this->bulk($body, 'delete');
    }

    /**
     * @inheritDoc
     */
    public function fetch(array $idsByIndex, array $fields = []): array
    {
        $docs = [];
        $requested = [];

        foreach ($idsByIndex as $index => $ids) {
            $this->assertName((string) $index);

            foreach (array_unique(array_map('strval', $ids)) as $id) {
                $doc = ['_index' => (string) $index, '_id' => $id];

                if ($fields !== []) {
                    $doc['_source'] = array_values($fields);
                }

                $docs[] = $doc;
                $requested[] = (string) $index;
            }
        }

        if ($docs === []) {
            return [];
        }

        $answer = (array) $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->mget(
                ['body' => ['docs' => $docs]] + $options
            ),
            (string) __('read documents'),
            true
        );

        return $this->responses->documents((array) ($answer['docs'] ?? []), $requested);
    }

    /**
     * @param array<int, mixed> $body
     */
    private function bulk(array $body, string $operation): WriteResultInterface
    {
        $answer = (array) $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->bulk(['body' => $body] + $options),
            (string) __('write documents')
        );

        return $this->responses->writeResult($answer, $operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(string $index, string $id, int $version): array
    {
        return ['_index' => $index, '_id' => $id, 'version' => $version, 'version_type' => 'external_gte'];
    }

    private function assertName(string $name): void
    {
        if (preg_match(self::VALID_NAME, $name) !== 1) {
            throw new InvalidArgumentException((string) __('"%1" is not a valid index or alias name.', $name));
        }
    }
}
