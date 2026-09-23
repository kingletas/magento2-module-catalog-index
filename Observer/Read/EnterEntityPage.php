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
use Magento\Framework\App\Action\AbstractAction;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Records which product or category a storefront page is for, before the controller loads anything.
 */
class EnterEntityPage implements ObserverInterface
{
    /**
     * @param string[] $actions Full action names that are this page, such as catalog_product_view.
     */
    public function __construct(
        private readonly PageScope $scope,
        private readonly string $page,
        private readonly array $actions = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $request = $this->requestOf($observer);

        if ($request === null || !in_array($request->getFullActionName(), $this->actions, true)) {
            return;
        }

        $this->scope->enter(PageType::from($this->page), (int) $request->getParam('id'));
    }

    private function requestOf(Observer $observer): ?HttpRequest
    {
        $action = $observer->getEvent()->getData('controller_action');
        $request = $action instanceof AbstractAction
            ? $action->getRequest()
            : $observer->getEvent()->getData('request');

        return $request instanceof HttpRequest ? $request : null;
    }
}
