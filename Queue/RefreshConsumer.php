<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Queue;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Kingletas\CatalogIndex\Model\Update\RefreshRequest;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Applies queued refresh requests from either lane.
 */
class RefreshConsumer
{
    public function __construct(
        private readonly RefresherPool $refreshers,
        private readonly FullRebuild $fullRebuild,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function process(string $message): void
    {
        try {
            $request = $this->decode($message);
        } catch (Throwable $e) {
            $this->logger->warning(
                'Catalog index: a refresh message was unreadable and was dropped.',
                ['exception' => $e]
            );

            return;
        }

        try {
            if ($request->isFull()) {
                $this->fullRebuild->run($request->family);

                return;
            }

            $this->refreshers->get($request->family)->refresh($request->ids);
        } catch (Throwable $e) {
            // The change log, reservation sweep and drift check revisit these ids, so redelivery only blocks the queue.
            $this->logger->error(
                sprintf('Catalog index: a %s refresh of %d ids failed.', $request->family->value, count($request->ids)),
                ['exception' => $e]
            );
        }
    }

    private function decode(string $message): RefreshRequest
    {
        $data = $this->json->unserialize($message);

        if (!is_array($data) || !is_array($data['ids'] ?? null)) {
            throw new InvalidArgumentException((string) __('A refresh message must be an object with an ids list.'));
        }

        $family = IndexFamily::tryFrom((string) ($data['family'] ?? ''))
            ?? throw new InvalidArgumentException((string) __('A refresh message names no known family.'));

        return new RefreshRequest($family, $data['ids'], (string) ($data['reason'] ?? RefreshRequest::REASON_MANUAL));
    }
}
