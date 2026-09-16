<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Rebuild;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Magento\Framework\Mview\ViewInterfaceFactory;

/**
 * Reads a family's change log, so a rebuild can replay whatever changed while it ran.
 */
class ChangelogReader
{
    /**
     * @param array<string, string> $viewIds Family value to mview view id.
     */
    public function __construct(
        private readonly ViewInterfaceFactory $viewFactory,
        private readonly array $viewIds = []
    ) {
    }

    /**
     * @return int|null Null when the family is not tracked on schedule, so there is nothing to replay.
     */
    public function currentVersion(IndexFamily $family): ?int
    {
        $viewId = $this->viewIds[$family->value] ?? null;

        if ($viewId === null) {
            return null;
        }

        $view = $this->viewFactory->create()->load($viewId);

        return $view->isEnabled() ? (int) $view->getChangelog()->getVersion() : null;
    }

    /**
     * @return int|null Change log entries not yet processed, or null when the family is not on schedule.
     */
    public function backlog(IndexFamily $family): ?int
    {
        $viewId = $this->viewIds[$family->value] ?? null;

        if ($viewId === null) {
            return null;
        }

        $view = $this->viewFactory->create()->load($viewId);

        if (!$view->isEnabled()) {
            return null;
        }

        return max(0, (int) $view->getChangelog()->getVersion() - (int) $view->getState()->getVersionId());
    }

    /**
     * @return int[]
     */
    public function idsBetween(IndexFamily $family, int $fromVersion, int $toVersion): array
    {
        $viewId = $this->viewIds[$family->value] ?? null;

        if ($viewId === null || $toVersion <= $fromVersion) {
            return [];
        }

        $ids = $this->viewFactory->create()->load($viewId)->getChangelog()->getList($fromVersion, $toVersion);

        return array_values(array_unique(array_map('intval', (array) $ids)));
    }
}
