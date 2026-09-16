<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\OpenSearch;

use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\OpenSearch\ClientGateway;
use Kingletas\CatalogIndex\Model\OpenSearch\ConnectionSettings;
use Kingletas\CatalogIndex\Model\OpenSearch\ConnectionSettingsProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\OpenSearch\Model\SearchClient;
use Magento\OpenSearch\Model\SearchClientFactory;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearch\Common\Exceptions\NoNodesAvailableException;
use PHPUnit\Framework\TestCase;

class ClientGatewayTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $built = [];

    public function testTheClientIsBuiltOnceFromTheConnectionSettings(): void
    {
        $gateway = $this->gateway();

        $gateway->send(static fn (): array => ['acknowledged' => true], 'ping');
        $answer = $gateway->send(static fn (Client $client, array $options): array => $options, 'ping', true);

        $this->assertCount(1, $this->built);
        $this->assertSame('https://search.test:9443', $this->built[0]['options']['hostname']);
        $this->assertSame(1, $this->built[0]['options']['enableAuth']);
        $this->assertSame(['client' => ['timeout' => 0.4, 'connect_timeout' => 0.2]], $answer);
    }

    public function testAnUnreachableStoreBecomesADocumentStoreException(): void
    {
        $this->expectException(DocumentStoreException::class);
        $this->expectExceptionMessage('could not read documents');

        $this->gateway()->send(static function (): never {
            throw new NoNodesAvailableException('No alive nodes found in your cluster');
        }, 'read documents');
    }

    public function testNotFoundIsNullOnlyWhenTheCallerSaysSo(): void
    {
        $missing = static function (): never {
            throw new Missing404Exception('{"status":404}');
        };

        $this->assertNull($this->gateway()->send($missing, 'read alias', true, true));

        $this->expectException(DocumentStoreException::class);
        $this->gateway()->send($missing, 'refresh index');
    }

    public function testAConnectionMagentoRefusesToBuildIsReportedAsAStoreFailure(): void
    {
        $factory = $this->createMock(SearchClientFactory::class);
        $factory->method('create')->willThrowException(
            new LocalizedException(new Phrase('The search failed because of a search engine misconfiguration.'))
        );

        $this->expectException(DocumentStoreException::class);
        $this->expectExceptionMessage('misconfiguration');

        (new ClientGateway($factory, $this->settings()))->send(static fn (): array => [], 'ping');
    }

    public function testAnAnswerThatIsNotAnArrayReadsAsEmpty(): void
    {
        $this->assertSame([], $this->gateway()->send(static fn (): bool => true, 'ping'));
    }

    private function gateway(): ClientGateway
    {
        $searchClient = $this->createMock(SearchClient::class);
        $searchClient->method('getOpenSearchClient')->willReturn($this->createMock(Client::class));
        $factory = $this->createMock(SearchClientFactory::class);
        $factory->method('create')->willReturnCallback(function (array $arguments) use ($searchClient): SearchClient {
            $this->built[] = $arguments;

            return $searchClient;
        });

        return new ClientGateway($factory, $this->settings());
    }

    private function settings(): ConnectionSettingsProvider
    {
        $settings = $this->createMock(ConnectionSettingsProvider::class);
        $settings->method('get')->willReturn(
            new ConnectionSettings('https://search.test:9443', 'reader', 'secret', 0.4, 30.0)
        );

        return $settings;
    }
}
