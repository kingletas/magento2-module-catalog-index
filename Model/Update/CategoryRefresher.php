<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Api\CachePurgerInterface;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Model\Build\CategoryDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;

/**
 * Keeps category documents current in every store view.
 */
class CategoryRefresher implements RefresherInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly CategoryDocumentBuilder $builder,
        private readonly DocumentWriter $writer,
        private readonly PurgePlanner $planner,
        private readonly CachePurgerInterface $purger,
        private readonly VersionSource $versions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function family(): IndexFamily
    {
        return IndexFamily::Category;
    }

    /**
     * @inheritDoc
     */
    public function refresh(array $ids): void
    {
        if (!$this->config->isEnabled() || $ids === []) {
            return;
        }

        $changes = new ChangeSet();

        foreach ($this->scopes->storeIds() as $storeId) {
            $alias = $this->namer->alias(IndexFamily::Category, $storeId);

            foreach (array_chunk($ids, $this->config->getBatchSize()) as $chunk) {
                $changes->merge($this->refreshInto($chunk, $storeId, $alias, $alias));
            }
        }

        $this->purger->purge($this->planner->forCategories($changes));
    }

    /**
     * @inheritDoc
     */
    public function refreshInto(array $ids, int $scopeId, string $writeIndex, string $compareIndex): ChangeSet
    {
        $batch = $this->builder->build(
            array_map('intval', $ids),
            $scopeId,
            $this->scopes->rootCategoryOf($scopeId),
            $this->versions->next()
        );

        return $this->writer->replace($writeIndex, $compareIndex, $batch, (string) $scopeId);
    }
}
