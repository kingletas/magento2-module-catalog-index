<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Support;

use Kingletas\CatalogIndex\Model\OpenSearch\ClientGateway;
use Kingletas\CatalogIndex\Model\OpenSearch\ConnectionSettings;
use Kingletas\CatalogIndex\Model\OpenSearch\ConnectionSettingsProvider;
use Magento\OpenSearch\Model\SearchClient;
use Magento\OpenSearch\Model\SearchClientFactory;
use OpenSearch\Client;
use OpenSearch\Namespaces\CatNamespace;
use OpenSearch\Namespaces\IndicesNamespace;
use Throwable;

/**
 * Magento's OpenSearch client, answering each call from a queue and recording what was sent.
 */
trait OpenSearchClientDouble
{
    /** @var array<int, array{0: string, 1: array<string, mixed>}> Call name and parameters, in order. */
    protected array $sent = [];

    /** @var array<int, mixed> Answers or Throwables, taken in call order. */
    protected array $answers = [];

    protected function gateway(): ClientGateway
    {
        $record = function (string $call): callable {
            return function (array $params = []) use ($call): mixed {
                $this->sent[] = [$call, $params];
                $answer = array_shift($this->answers) ?? [];

                if ($answer instanceof Throwable) {
                    throw $answer;
                }

                return $answer;
            };
        };

        $indices = $this->createMock(IndicesNamespace::class);
        foreach (['create', 'refresh', 'updateAliases', 'getAlias', 'delete'] as $call) {
            $indices->method($call)->willReturnCallback($record('indices.' . $call));
        }

        $cat = $this->createMock(CatNamespace::class);
        $cat->method('indices')->willReturnCallback($record('cat.indices'));

        $client = $this->createMock(Client::class);
        foreach (['bulk', 'mget', 'count'] as $call) {
            $client->method($call)->willReturnCallback($record($call));
        }
        $client->method('indices')->willReturn($indices);
        $client->method('cat')->willReturn($cat);

        $searchClient = $this->createMock(SearchClient::class);
        $searchClient->method('getOpenSearchClient')->willReturn($client);
        $factory = $this->createMock(SearchClientFactory::class);
        $factory->method('create')->willReturn($searchClient);
        $settings = $this->createMock(ConnectionSettingsProvider::class);
        $settings->method('get')->willReturn(new ConnectionSettings('http://search.test:9200', '', '', 0.4, 30.0));

        return new ClientGateway($factory, $settings);
    }
}
