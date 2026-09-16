<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\IndexAdminInterface;
use OpenSearch\Client;

/**
 * Manages OpenSearch indexes and aliases, so a rebuild goes live in one request.
 */
class OpenSearchIndexAdmin implements IndexAdminInterface
{
    private const string VALID_NAME = '/^[a-z0-9][a-z0-9_\-]*$/';

    public function __construct(
        private readonly ClientGateway $gateway
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createIndex(string $index, array $definition): void
    {
        $this->assertName($index);
        $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->indices()->create(
                ['index' => $index, 'body' => $definition] + $options
            ),
            (string) __('create index %1', $index)
        );
    }

    /**
     * @inheritDoc
     */
    public function refresh(string $index): void
    {
        $this->assertName($index);
        $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->indices()->refresh(
                ['index' => $index] + $options
            ),
            (string) __('refresh index %1', $index)
        );
    }

    /**
     * @inheritDoc
     */
    public function pointAlias(string $alias, string $index): ?string
    {
        $this->assertName($alias);
        $this->assertName($index);
        $holders = $this->aliasHolders($alias);
        $actions = [];

        foreach ($holders as $holder) {
            if ($holder !== $index) {
                $actions[] = ['remove' => ['index' => $holder, 'alias' => $alias]];
            }
        }

        $actions[] = ['add' => ['index' => $index, 'alias' => $alias]];
        $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->indices()->updateAliases(
                ['body' => ['actions' => $actions]] + $options
            ),
            (string) __('move alias %1 to %2', $alias, $index)
        );

        return $holders === [] ? null : (string) end($holders);
    }

    /**
     * @inheritDoc
     */
    public function resolveAlias(string $alias): ?string
    {
        $this->assertName($alias);
        $holders = $this->aliasHolders($alias);

        return $holders === [] ? null : (string) end($holders);
    }

    /**
     * @inheritDoc
     */
    public function dropIndex(string $index): void
    {
        $this->assertName($index);
        $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->indices()->delete(
                ['index' => $index] + $options
            ),
            (string) __('drop index %1', $index),
            false,
            true
        );
    }

    /**
     * @inheritDoc
     */
    public function count(string $index): int
    {
        $this->assertName($index);
        $answer = $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->count(['index' => $index] + $options),
            (string) __('count index %1', $index),
            true,
            true
        );

        return (int) ($answer['count'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function listIndexes(string $prefix): array
    {
        $this->assertName($prefix);
        $rows = $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->cat()->indices(
                ['index' => $prefix . '*', 'format' => 'json', 'h' => 'index'] + $options
            ),
            (string) __('list indexes under %1', $prefix),
            true,
            true
        ) ?? [];
        $names = [];

        foreach ($rows as $row) {
            if (is_array($row) && isset($row['index']) && str_starts_with((string) $row['index'], $prefix)) {
                $names[] = (string) $row['index'];
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @return string[] Indexes the alias points at, sorted by name.
     */
    private function aliasHolders(string $alias): array
    {
        $answer = $this->gateway->send(
            static fn (Client $client, array $options): mixed => $client->indices()->getAlias(
                ['name' => $alias] + $options
            ),
            (string) __('read alias %1', $alias),
            true,
            true
        ) ?? [];
        $indexes = array_map('strval', array_keys($answer));
        sort($indexes);

        return $indexes;
    }

    private function assertName(string $name): void
    {
        if (preg_match(self::VALID_NAME, $name) !== 1) {
            throw new InvalidArgumentException((string) __('"%1" is not a valid index or alias name.', $name));
        }
    }
}
