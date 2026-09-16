<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends refresh requests down the lane the configured update mode chooses, and never lets a failure escape.
 */
class RefreshPublisher
{
    public function __construct(
        private readonly Config $config,
        private readonly PublisherInterface $publisher,
        private readonly RefresherPool $refreshers,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly string $priorityTopic = 'kingletas.catalog_index.priority',
        private readonly string $refreshTopic = 'kingletas.catalog_index.refresh'
    ) {
    }

    /**
     * @param array<int|string> $ids
     */
    public function publish(IndexFamily $family, array $ids, string $reason): void
    {
        try {
            $this->dispatch(new RefreshRequest($family, $ids, $reason));
        } catch (Throwable $e) {
            $this->logger->error(
                sprintf('Catalog index: a %s refresh for %s could not be sent.', $family->value, $reason),
                ['exception' => $e]
            );
        }
    }

    public function publishFull(IndexFamily $family): void
    {
        $this->publish($family, [], RefreshRequest::REASON_FULL);
    }

    private function dispatch(RefreshRequest $request): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $mode = $this->config->getUpdateMode();

        if ($mode === Config::MODE_SCHEDULE && $request->isInChangelog()) {
            return;
        }

        if ($mode === Config::MODE_INLINE && $this->fitsInline($request)) {
            $this->refreshers->get($request->family)->refresh($request->ids);

            return;
        }

        $topic = $request->family->isPriority() ? $this->priorityTopic : $this->refreshTopic;
        $chunks = $request->isFull() ? [[]] : array_chunk($request->ids, $this->config->getBatchSize());

        foreach ($chunks as $chunk) {
            $message = new RefreshRequest($request->family, $chunk, $request->reason);
            $this->publisher->publish($topic, (string) $this->json->serialize($message->toArray()));
        }
    }

    private function fitsInline(RefreshRequest $request): bool
    {
        return $request->mayRunInline() && count($request->ids) <= $this->config->getInlineLimit();
    }
}
