<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer;

use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the request's read counters once, when the response is ready to send.
 */
class FlushReadMetrics implements ObserverInterface
{
    public function __construct(
        private readonly FallbackRecorder $recorder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function execute(Observer $observer)
    {
        try {
            $this->recorder->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Catalog index: read counters could not be saved.', ['exception' => $e]);
        }
    }
}
