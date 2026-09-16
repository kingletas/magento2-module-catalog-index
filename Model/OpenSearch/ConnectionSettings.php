<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

/**
 * Where the document store is and how long to wait for it.
 */
class ConnectionSettings
{
    public function __construct(
        public readonly string $baseUri,
        public readonly string $username,
        public readonly string $password,
        public readonly float $readTimeout,
        public readonly float $writeTimeout,
        public readonly float $connectTimeout = 0.2
    ) {
    }

    public function hasCredentials(): bool
    {
        return $this->username !== '';
    }

    /**
     * The options Magento's OpenSearch client is built from, with the port already part of the host.
     *
     * @return array<string, mixed>
     */
    public function clientOptions(): array
    {
        return [
            'hostname' => $this->baseUri,
            'port' => '',
            'enableAuth' => $this->hasCredentials() ? 1 : 0,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => $this->writeTimeout,
        ];
    }
}
