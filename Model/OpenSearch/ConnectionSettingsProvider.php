<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

use Kingletas\CatalogIndex\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Builds connection settings from the store's search engine or from this module's own fields.
 */
class ConnectionSettingsProvider
{
    private ?ConnectionSettings $settings = null;

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly string $catalogSearchPrefix = 'catalog/search/opensearch_'
    ) {
    }

    public function get(): ConnectionSettings
    {
        return $this->settings ??= $this->config->usesCatalogSearchConnection()
            ? $this->fromCatalogSearch()
            : $this->fromOwnFields();
    }

    private function fromCatalogSearch(): ConnectionSettings
    {
        $auth = (bool) $this->scopeConfig->getValue($this->catalogSearchPrefix . 'enable_auth');

        return $this->settings(
            (string) $this->scopeConfig->getValue($this->catalogSearchPrefix . 'server_hostname'),
            (int) $this->scopeConfig->getValue($this->catalogSearchPrefix . 'server_port'),
            false,
            $auth ? (string) $this->scopeConfig->getValue($this->catalogSearchPrefix . 'username') : '',
            $auth ? (string) $this->scopeConfig->getValue($this->catalogSearchPrefix . 'password') : ''
        );
    }

    private function fromOwnFields(): ConnectionSettings
    {
        $auth = $this->config->isAuthEnabled();

        return $this->settings(
            $this->config->getHostname(),
            $this->config->getPort(),
            $this->config->isHttps(),
            $auth ? $this->config->getUsername() : '',
            $auth ? $this->config->getPassword() : ''
        );
    }

    private function settings(
        string $host,
        int $port,
        bool $https,
        string $username,
        string $password
    ): ConnectionSettings {
        $host = trim($host) === '' ? 'localhost' : trim($host);

        if (preg_match('#^https?://#', $host) !== 1) {
            $host = ($https ? 'https://' : 'http://') . $host;
        }

        $host = rtrim($host, '/');
        $baseUri = $port > 0 && preg_match('#:\d+$#', $host) !== 1 ? $host . ':' . $port : $host;

        return new ConnectionSettings(
            $baseUri,
            $username,
            $password,
            $this->config->getReadTimeoutMs() / 1000,
            (float) $this->config->getWriteTimeoutSeconds()
        );
    }
}
