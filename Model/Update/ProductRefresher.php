<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Api\CachePurgerInterface;
use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Cache\PurgePlanner;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Keeps product documents current in every store view.
 */
class ProductRefresher implements RefresherInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly ProductDocumentBuilder $builder,
        private readonly DocumentWriter $writer,
        private readonly AffectedProductResolver $relations,
        private readonly PurgePlanner $planner,
        private readonly CachePurgerInterface $purger,
        private readonly ScheduleStorage $schedule,
        private readonly VersionSource $versions,
        private readonly ClockInterface $clock,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritDoc
     */
    public function family(): IndexFamily
    {
        return IndexFamily::Product;
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
            $alias = $this->namer->alias(IndexFamily::Product, $storeId);

            foreach (array_chunk($ids, $this->config->getBatchSize()) as $chunk) {
                $changes->merge($this->refreshInto($chunk, $storeId, $alias, $alias));
            }
        }

        $this->purger->purge($this->planner->forProducts($changes));
    }

    /**
     * @inheritDoc
     */
    public function refreshInto(array $ids, int $scopeId, string $writeIndex, string $compareIndex): ChangeSet
    {
        $version = $this->versions->next();
        $context = new BuildContext(
            $scopeId,
            $this->scopes->websiteIdOf($scopeId),
            $version,
            $this->clock->now(),
            (string) $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, (string) $scopeId)
        );
        $batch = $this->builder->build($this->relations->withParents($ids), $context);
        $this->schedule->record(IndexFamily::Product, $scopeId, $batch->refreshMoments);

        return $this->writer->replace($writeIndex, $compareIndex, $batch, (string) $scopeId);
    }
}
