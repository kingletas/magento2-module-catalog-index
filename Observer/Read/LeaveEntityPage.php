<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Observer\Read;

use Kingletas\CatalogIndex\Model\Read\PageScope;
use Kingletas\CatalogIndex\Api\Data\PageType;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Stops serving from documents once the page's own product or category has been loaded.
 */
class LeaveEntityPage implements ObserverInterface
{
    public function __construct(
        private readonly PageScope $scope,
        private readonly string $page
    ) {
    }

    /**
     * @inheritDoc
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function execute(Observer $observer)
    {
        $this->scope->leave(PageType::from($this->page));
    }
}
