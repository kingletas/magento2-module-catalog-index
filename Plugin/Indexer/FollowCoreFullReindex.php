<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Plugin\Indexer;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;

/**
 * Queues a family rebuild after Magento fully rebuilds a core index it reads, which swaps tables and fires no trigger.
 */
class FollowCoreFullReindex
{
    public function __construct(
        private readonly RefreshPublisher $publisher,
        private readonly string $family
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterExecuteFull(object $subject, mixed $result = null): mixed
    {
        $this->publisher->publishFull(IndexFamily::from($this->family));

        return $result;
    }
}
