<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Api\IndexAdminInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Store\WriteResult;

/**
 * A document store that keeps OpenSearch's rules: older versions are refused and aliases resolve to one index.
 */
class InMemoryDocumentStore implements DocumentStoreInterface, IndexAdminInterface
{
    /** @var array<string, array<string, DocumentInterface>> */
    public array $indexes = [];

    /** @var array<string, string> */
    public array $aliases = [];

    /** @var array<string, int> Operation name to times called. */
    public array $calls = [];

    public bool $unreachable = false;

    public function write(string $index, array $documents): WriteResult
    {
        $this->tally('write');
        $target = $this->resolve($index, true);
        $written = [];
        $stale = [];

        foreach ($documents as $document) {
            $current = $this->indexes[$target][$document->getId()] ?? null;

            if ($current !== null && $current->getVersion() > $document->getVersion()) {
                $stale[] = $document->getId();

                continue;
            }

            $this->indexes[$target][$document->getId()] = $document;
            $written[] = $document->getId();
        }

        return new WriteResult($written, $stale);
    }

    public function delete(string $index, array $ids, int $version): WriteResult
    {
        $this->tally('delete');
        $target = $this->resolve($index, true);
        $written = [];
        $stale = [];

        foreach ($ids as $id) {
            $current = $this->indexes[$target][(string) $id] ?? null;

            if ($current !== null && $current->getVersion() > $version) {
                $stale[] = (string) $id;

                continue;
            }

            unset($this->indexes[$target][(string) $id]);
            $written[] = (string) $id;
        }

        return new WriteResult($written, $stale);
    }

    public function fetch(array $idsByIndex, array $fields = []): array
    {
        $this->tally('fetch');
        $found = [];

        foreach ($idsByIndex as $index => $ids) {
            $target = $this->resolve((string) $index, false);

            foreach ($ids as $id) {
                $document = $this->indexes[$target][(string) $id] ?? null;

                if ($document !== null) {
                    $found[(string) $index][(string) $id] = $fields === []
                        ? $document
                        : new Document(
                            $document->getId(),
                            $document->getVersion(),
                            array_intersect_key($document->getSource(), array_flip($fields))
                        );
                }
            }
        }

        return $found;
    }

    public function createIndex(string $index, array $definition): void
    {
        $this->tally('createIndex');
        $this->indexes[$index] ??= [];
    }

    public function refresh(string $index): void
    {
        $this->tally('refresh');
    }

    public function pointAlias(string $alias, string $index): ?string
    {
        $this->tally('pointAlias');
        $previous = $this->aliases[$alias] ?? null;
        $this->aliases[$alias] = $index;

        return $previous;
    }

    public function resolveAlias(string $alias): ?string
    {
        $this->tally('resolveAlias');

        return $this->aliases[$alias] ?? null;
    }

    public function dropIndex(string $index): void
    {
        $this->tally('dropIndex');
        unset($this->indexes[$index]);
    }

    public function count(string $index): int
    {
        $this->tally('count');

        return count($this->indexes[$this->resolve($index, false)] ?? []);
    }

    public function listIndexes(string $prefix): array
    {
        $this->tally('listIndexes');
        $names = array_values(array_filter(
            array_keys($this->indexes),
            static fn (string $name): bool => str_starts_with($name, $prefix)
        ));
        sort($names);

        return $names;
    }

    /**
     * @param array<string, mixed> $source
     */
    public function seed(string $index, string $id, array $source, int $version = 1): void
    {
        $this->indexes[$index][$id] = new Document($id, $version, $source);
    }

    public function calls(string $operation): int
    {
        return $this->calls[$operation] ?? 0;
    }

    private function resolve(string $name, bool $forWrite): string
    {
        $target = $this->aliases[$name] ?? $name;

        if ($forWrite) {
            $this->indexes[$target] ??= [];
        }

        return $target;
    }

    private function tally(string $operation): void
    {
        $this->calls[$operation] = ($this->calls[$operation] ?? 0) + 1;

        if ($this->unreachable) {
            throw new DocumentStoreException('The document store is unreachable.');
        }
    }
}
