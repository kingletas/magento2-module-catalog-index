<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Console\Command;

use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\FullRebuild;
use Kingletas\CatalogIndex\Model\Update\RefresherPool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Rebuilds whole families into fresh indexes, or refreshes named ids in the live ones.
 */
class RebuildCommand extends Command
{
    private const string OPTION_FAMILY = 'family';
    private const string OPTION_SCOPE = 'scope';
    private const string OPTION_IDS = 'ids';

    public function __construct(
        private readonly FullRebuild $fullRebuild,
        private readonly RefresherPool $refreshers,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription((string) __('Rebuild catalog documents into a fresh index and switch to it.'))
            ->addOption(
                self::OPTION_FAMILY,
                'f',
                InputOption::VALUE_REQUIRED,
                (string) __('product, price, stock, category or all.'),
                'all'
            )
            ->addOption(
                self::OPTION_SCOPE,
                's',
                InputOption::VALUE_REQUIRED,
                (string) __('One store view id, or website id for price and stock.')
            )
            ->addOption(
                self::OPTION_IDS,
                'i',
                InputOption::VALUE_REQUIRED,
                (string) __('Comma-separated ids to refresh in place instead.')
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $families = $this->families((string) $input->getOption(self::OPTION_FAMILY));

        if ($families === []) {
            $output->writeln('<error>' . __('--family must be product, price, stock, category or all.') . '</error>');

            return Command::INVALID;
        }

        $ids = array_filter(array_map('intval', explode(',', (string) $input->getOption(self::OPTION_IDS))));
        $scope = $input->getOption(self::OPTION_SCOPE);

        try {
            foreach ($families as $family) {
                if ($ids !== []) {
                    $this->refresh($family, $ids, $output);

                    continue;
                }

                $this->rebuild($family, $scope === null ? null : (int) $scope, $output);
            }
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function rebuild(IndexFamily $family, ?int $scope, OutputInterface $output): void
    {
        foreach ($this->fullRebuild->run($family, $scope) as $report) {
            $output->writeln((string) ($report->isSkipped()
                ? __('%1 %2: skipped, %3', $family->value, $report->scopeId, $report->skippedBecause)
                : __(
                    '%1 %2: %3 documents in %4, %5 changed, %6 replayed, %7 refused',
                    $family->value,
                    $report->scopeId,
                    $report->documents,
                    $report->index,
                    $report->changed,
                    $report->replayed,
                    $report->failed
                )));
        }
    }

    /**
     * @param int[] $ids
     */
    private function refresh(IndexFamily $family, array $ids, OutputInterface $output): void
    {
        $this->refreshers->get($family)->refresh($ids);
        $output->writeln((string) __('%1: refreshed %2 ids in the live indexes', $family->value, count($ids)));
    }

    /**
     * @return IndexFamily[]
     */
    private function families(string $option): array
    {
        if ($option === 'all') {
            return IndexFamily::cases();
        }

        $family = IndexFamily::tryFrom($option);

        return $family === null ? [] : [$family];
    }
}
