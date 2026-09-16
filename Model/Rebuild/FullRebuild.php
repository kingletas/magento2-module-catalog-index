<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Rebuild;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\Foundation\Model\Lock\LockRunner;
use Kingletas\CatalogIndex\Api\IndexAdminInterface;
use Kingletas\CatalogIndex\Api\RefresherInterface;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexDefinition;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Throwable;

/**
 * Builds a whole family into a fresh index, replays what changed meanwhile, then moves the alias in one step.
 */
class FullRebuild
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly IndexDefinition $definition,
        private readonly IndexAdminInterface $indexes,
        private readonly RefresherPool $refreshers,
        private readonly IdSource $idSource,
        private readonly ChangelogReader $changelog,
        private readonly StateStorage $state,
        private readonly RebuildPurger $purger,
        private readonly LockRunner $locks,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @return RebuildReport[]
     */
    public function run(IndexFamily $family, ?int $onlyScope = null): array
    {
        if (!$this->config->isEnabled()) {
            return [new RebuildReport($family, 0, skippedBecause: (string) __('the catalog index is disabled'))];
        }

        $reports = [];

        foreach ($this->scopes->scopeIds($family) as $scopeId) {
            if ($onlyScope === null || $onlyScope === $scopeId) {
                $reports[] = $this->runScope($family, $scopeId);
            }
        }

        return $reports;
    }

    private function runScope(IndexFamily $family, int $scopeId): RebuildReport
    {
        $alias = $this->namer->alias($family, $scopeId);
        $lock = 'kingletas_catalog_index_rebuild_' . $alias;

        $report = null;
        $ran = $this->locks->run($lock, function () use ($family, $scopeId, $alias, &$report): void {
            $report = $this->rebuild($family, $scopeId, $alias);
        });

        return $ran && $report instanceof RebuildReport
            ? $report
            : new RebuildReport(
                $family,
                $scopeId,
                skippedBecause: (string) __('another rebuild of this index is running')
            );
    }

    private function rebuild(IndexFamily $family, int $scopeId, string $alias): RebuildReport
    {
        $refresher = $this->refreshers->get($family);
        $startVersion = $this->changelog->currentVersion($family);
        $index = $this->namer->buildName($alias, $this->clock->now());
        $this->indexes->createIndex($index, $this->definition->for($family));

        try {
            $changes = new ChangeSet();

            foreach ($this->idSource->batches($family, $scopeId, $this->config->getBatchSize()) as $batch) {
                $changes->merge($refresher->refreshInto($batch, $scopeId, $index, $alias));
            }

            [$replayed, $replayVersion] = $this->replay($refresher, $changes, $scopeId, $index, $alias, $startVersion);
            $this->indexes->refresh($index);
            $previous = $this->indexes->pointAlias($alias, $index);
        } catch (Throwable $e) {
            $this->indexes->dropIndex($index);

            throw $e;
        }

        [$late] = $this->replay($refresher, $changes, $scopeId, $alias, $alias, $replayVersion);

        $documents = $this->indexes->count($index);
        $this->recordBuild($family, $scopeId, $index, $previous, $documents);
        $this->dropSuperseded($alias, $index);
        $this->purger->purge($family, $changes);

        return new RebuildReport(
            $family,
            $scopeId,
            $index,
            $previous,
            $documents,
            count($changes->changes()),
            $replayed + $late,
            count($changes->failed())
        );
    }

    /**
     * Replayed changes join the rebuild's own, so a page changed mid-build is purged with the rest.
     *
     * @return array{0: int, 1: int|null} Ids replayed and the version the replay reached.
     */
    private function replay(
        RefresherInterface $refresher,
        ChangeSet $changes,
        int $scopeId,
        string $writeIndex,
        string $compareIndex,
        ?int $fromVersion
    ): array {
        if ($fromVersion === null) {
            return [0, null];
        }

        $family = $refresher->family();
        $toVersion = (int) $this->changelog->currentVersion($family);
        $ids = $this->changelog->idsBetween($family, $fromVersion, $toVersion);

        foreach (array_chunk($ids, $this->config->getBatchSize()) as $chunk) {
            $changes->merge($refresher->refreshInto($chunk, $scopeId, $writeIndex, $compareIndex));
        }

        return [count($ids), $toVersion];
    }

    private function recordBuild(
        IndexFamily $family,
        int $scopeId,
        string $index,
        ?string $previous,
        int $documents
    ): void {
        $this->state->set('build:' . $family->value . ':' . $scopeId, [
            'index' => $index,
            'previous' => $previous,
            'built_at' => $this->clock->now()->format(DATE_ATOM),
            'documents' => $documents,
        ]);
    }

    private function dropSuperseded(string $alias, string $current): void
    {
        $older = array_values(array_filter(
            $this->indexes->listIndexes($this->namer->buildPrefix($alias)),
            static fn (string $name): bool => $name !== $current && $name < $current
        ));
        $keep = $this->config->getKeepPreviousBuilds();
        $drop = $keep === 0 ? $older : array_slice($older, 0, max(0, count($older) - $keep));

        foreach ($drop as $name) {
            $this->indexes->dropIndex($name);
        }
    }
}
