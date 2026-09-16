<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\OpenSearch;

use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Magento\OpenSearch\Model\SearchClient;
use Magento\OpenSearch\Model\SearchClientFactory;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearch\Exception\NotFoundHttpException;
use Throwable;

/**
 * Sends requests through Magento's own OpenSearch client, so connection, TLS and auth handling are Magento's.
 */
class ClientGateway
{
    private ?SearchClient $searchClient = null;

    public function __construct(
        private readonly SearchClientFactory $searchClientFactory,
        private readonly ConnectionSettingsProvider $settingsProvider
    ) {
    }

    /**
     * Any failure becomes a DocumentStoreException; with $missingAsNull a 404 answers null instead.
     *
     * @param callable(Client, array<string, mixed>): mixed $send Receives the client and per-request options.
     * @return array<mixed>|null
     * @throws DocumentStoreException
     */
    public function send(callable $send, string $action, bool $isRead = false, bool $missingAsNull = false): ?array
    {
        $settings = $this->settingsProvider->get();
        $options = [
            'timeout' => $isRead ? $settings->readTimeout : $settings->writeTimeout,
            'connect_timeout' => $settings->connectTimeout,
        ];

        try {
            $answer = $send($this->client(), ['client' => $options]);
        } catch (DocumentStoreException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($missingAsNull && $this->isNotFound($exception)) {
                return null;
            }

            throw new DocumentStoreException(
                (string) __('The document store could not %1: %2', $action, $exception->getMessage()),
                0,
                $exception
            );
        }

        return is_array($answer) ? $answer : [];
    }

    /**
     * @throws DocumentStoreException
     */
    private function client(): Client
    {
        try {
            $this->searchClient ??= $this->searchClientFactory->create([
                'options' => $this->settingsProvider->get()->clientOptions(),
            ]);

            return $this->searchClient->getOpenSearchClient();
        } catch (Throwable $exception) {
            throw new DocumentStoreException(
                (string) __('The document store connection is not configured: %1', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * The client in use throws one of two 404 exceptions depending on its version and transport.
     */
    private function isNotFound(Throwable $exception): bool
    {
        return is_a($exception, Missing404Exception::class) || is_a($exception, NotFoundHttpException::class);
    }
}
