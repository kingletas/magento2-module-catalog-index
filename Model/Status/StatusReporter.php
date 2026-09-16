<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Status;

use Kingletas\CatalogIndex\Api\IndexAdminInterface;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\Cache\ParkedPurges;
use Kingletas\CatalogIndex\Model\Config;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\CatalogIndex\Model\Rebuild\ChangelogReader;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Kingletas\CatalogIndex\Model\Staging\StagingMode;
use Magento\Framework\MessageQueue\DefaultValueProvider;

/**
 * Collects what an operator needs to know before trusting the index: builds, backlogs, fallbacks and the breaker.
 */
class StatusReporter
{
    public function __construct(
        private readonly Config $config,
        private readonly ScopeResolver $scopes,
        private readonly IndexNamer $namer,
        private readonly IndexAdminInterface $indexes,
        private readonly StateStorage $state,
        private readonly ChangelogReader $changelog,
        private readonly MetricStorage $metrics,
        private readonly CircuitBreaker $breaker,
        private readonly ScheduleStorage $schedule,
        private readonly StagingMode $stagingMode,
        private readonly DefaultValueProvider $queueDefaults,
        private readonly ParkedPurges $parkedPurges
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function summary(): array
    {
        return [
            (string) __('enabled') => $this->yesNo($this->config->isEnabled()),
            (string) __('update mode') => $this->config->getUpdateMode(),
            (string) __('queue connection') => (string) $this->queueDefaults->getConnection(),
            (string) __('staging watched') => $this->yesNo($this->stagingMode->isActive()),
            (string) __('scheduled refreshes') => (string) $this->schedule->pending(),
            (string) __('parked purges') => (string) $this->parkedPurges->count(),
            (string) __('read breaker') => (string) ($this->breaker->isOpen() ? __('open') : __('closed')),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function indexes(): array
    {
        $rows = [];

        foreach (IndexFamily::cases() as $family) {
            $backlog = $this->changelog->backlog($family);

            foreach ($this->scopes->scopeIds($family) as $scopeId) {
                $alias = $this->namer->alias($family, $scopeId);
                $build = $this->state->get('build:' . $family->value . ':' . $scopeId) ?? [];
                $rows[] = [
                    (string) __('index') => $alias,
                    (string) __('live') => $this->live($alias),
                    (string) __('documents') => $this->documents($alias),
                    (string) __('built') => (string) ($build['built_at'] ?? __('never')),
                    (string) __('backlog') => $backlog === null ? (string) __('not on schedule') : (string) $backlog,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function reads(int $hours): array
    {
        $rows = [];
        $budget = $this->config->getFallbackBudget();

        foreach ($this->metrics->totals($hours) as $page => $counts) {
            $fallback = array_sum($counts['fallback']);
            $total = $counts['served'] + $fallback;
            $ratio = $total === 0 ? 0.0 : $fallback / $total;
            arsort($counts['fallback']);
            $rows[] = [
                (string) __('page') => $page,
                (string) __('served') => (string) $counts['served'],
                (string) __('fell back') => (string) $fallback,
                (string) __('ratio') => sprintf('%.1f%%', $ratio * 100),
                (string) __('verdict') => (string) ($ratio > $budget ? __('over budget') : __('ok')),
                (string) __('top reason') => (string) (array_key_first($counts['fallback']) ?? '-'),
            ];
        }

        return $rows;
    }

    private function live(string $alias): string
    {
        try {
            return $this->indexes->resolveAlias($alias) ?? (string) __('missing');
        } catch (DocumentStoreException $e) {
            return (string) __('unreachable: %1', $e->getMessage());
        }
    }

    private function yesNo(bool $value): string
    {
        return (string) ($value ? __('yes') : __('no'));
    }

    private function documents(string $alias): string
    {
        try {
            return (string) $this->indexes->count($alias);
        } catch (DocumentStoreException) {
            return '?';
        }
    }
}
