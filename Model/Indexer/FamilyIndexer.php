<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Indexer;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Magento\Framework\Indexer\ActionInterface as IndexerAction;
use Magento\Framework\Mview\ActionInterface as MviewAction;

/**
 * Connects one document family to Magento's indexer and change log, so indexer:reindex and cron both drive it.
 */
class FamilyIndexer implements IndexerAction, MviewAction
{
    public function __construct(
        private readonly RefresherPool $refreshers,
        private readonly FullRebuild $fullRebuild,
        private readonly string $family
    ) {
    }

    /**
     * @inheritDoc
     */
    public function executeFull(): void
    {
        $this->fullRebuild->run($this->family());
    }

    /**
     * @param int[] $ids
     */
    public function executeList(array $ids): void
    {
        $this->refreshers->get($this->family())->refresh($ids);
    }

    /**
     * @param int $id
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function executeRow($id): void
    {
        $this->refreshers->get($this->family())->refresh([(int) $id]);
    }

    /**
     * @param int[] $ids
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    public function execute($ids): void
    {
        $this->refreshers->get($this->family())->refresh(array_map('intval', (array) $ids));
    }

    private function family(): IndexFamily
    {
        return IndexFamily::from($this->family);
    }
}
