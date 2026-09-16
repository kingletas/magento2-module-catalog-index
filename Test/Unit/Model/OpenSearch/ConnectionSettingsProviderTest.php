<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\OpenSearch;

use Kingletas\CatalogIndex\Model\OpenSearch\ConnectionSettingsProvider;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConnectionSettingsProviderTest extends TestCase
{
    use ShippedConfig;

    public function testTheCatalogSearchConnectionIsReusedByDefault(): void
    {
        $settings = (new ConnectionSettingsProvider($this->config(), $this->catalogSearch([
            'server_hostname' => 'opensearch.test',
            'server_port' => '9200',
            'enable_auth' => '1',
            'username' => 'magento',
            'password' => 'secret',
        ])))->get();

        $this->assertSame('http://opensearch.test:9200', $settings->baseUri);
        $this->assertSame('magento', $settings->username);
        $this->assertTrue($settings->hasCredentials());
        $this->assertSame(0.4, $settings->readTimeout);
        $this->assertSame(30.0, $settings->writeTimeout);
    }

    public function testCredentialsAreLeftOutWhenAuthenticationIsOff(): void
    {
        $settings = (new ConnectionSettingsProvider($this->config(), $this->catalogSearch([
            'server_hostname' => 'https://opensearch.test:9443',
            'server_port' => '9200',
            'enable_auth' => '0',
            'username' => 'ignored',
        ])))->get();

        $this->assertSame('https://opensearch.test:9443', $settings->baseUri);
        $this->assertFalse($settings->hasCredentials());
    }

    public function testTheModulesOwnFieldsApplyWhenChosen(): void
    {
        $config = $this->config([
            'connection/use_catalog_search' => '0',
            'connection/hostname' => 'documents.test',
            'connection/port' => '9443',
            'connection/https' => '1',
            'connection/enable_auth' => '1',
            'connection/username' => 'reader',
            'connection/password' => 'cipher',
            'connection/read_timeout_ms' => '250',
        ]);

        $settings = (new ConnectionSettingsProvider($config, $this->catalogSearch([])))->get();

        $this->assertSame('https://documents.test:9443', $settings->baseUri);
        $this->assertSame('decrypted:cipher', $settings->password);
        $this->assertSame(0.25, $settings->readTimeout);
    }

    /**
     * @param array<string, string> $values
     */
    private function catalogSearch(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $values[substr($path, strlen('catalog/search/opensearch_'))] ?? null
        );

        return $scopeConfig;
    }
}
