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
use Kingletas\CatalogIndex\Model\Build\StockDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;

/**
 * Keeps stock documents current in every website, parents included, because a parent shows its variants' stock.
 */
class StockRefresher implements RefresherInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly StockDocumentBuilder $builder,
        private readonly DocumentWriter $writer,
        private readonly AffectedProductResolver $relations,
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
        return IndexFamily::Stock;
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

        foreach ($this->scopes->websiteIds() as $websiteId) {
            $alias = $this->namer->alias(IndexFamily::Stock, $websiteId);

            foreach (array_chunk($ids, $this->config->getBatchSize()) as $chunk) {
                $changes->merge($this->refreshInto($chunk, $websiteId, $alias, $alias));
            }
        }

        $categories = $this->relations->categoriesOf($this->planner->salabilityFlips($changes));
        $this->purger->purge($this->planner->forStock($changes, $categories));
    }

    /**
     * @inheritDoc
     */
    public function refreshInto(array $ids, int $scopeId, string $writeIndex, string $compareIndex): ChangeSet
    {
        $batch = $this->builder->build($this->relations->withParents($ids), $scopeId, $this->versions->next());

        return $this->writer->replace($writeIndex, $compareIndex, $batch, (string) $scopeId);
    }
}
