<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Console\Command;

use Kingletas\CatalogIndex\Model\Drift\DriftVerifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compares a sample of stored product documents with a fresh build and says how many differ.
 */
class VerifyCommand extends Command
{
    private const string OPTION_SAMPLE = 'sample';
    private const string OPTION_NO_REPAIR = 'no-repair';

    public function __construct(
        private readonly DriftVerifier $verifier,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription((string) __('Check a sample of product documents against the database.'))
            ->addOption(
                self::OPTION_SAMPLE,
                null,
                InputOption::VALUE_REQUIRED,
                (string) __('Products to check per store view.')
            )
            ->addOption(
                self::OPTION_NO_REPAIR,
                null,
                InputOption::VALUE_NONE,
                (string) __('Report drift without rebuilding.')
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sample = $input->getOption(self::OPTION_SAMPLE);
        $reports = $this->verifier->verify(
            $sample === null ? null : max(1, (int) $sample),
            !$input->getOption(self::OPTION_NO_REPAIR)
        );

        if ($reports === []) {
            $output->writeln((string) __('The catalog index is disabled, so there is nothing to verify.'));

            return Command::SUCCESS;
        }

        $drift = false;

        foreach ($reports as $report) {
            $drift = $drift || $report->drifted !== [];
            $line = (string) __(
                'store %1: %2 of %3 differed',
                $report->storeId,
                count($report->drifted),
                $report->checked
            );

            if ($report->drifted !== []) {
                $line .= ' (' . implode(', ', array_slice($report->drifted, 0, 20)) . ')';
            }

            $output->writeln($report->repaired ? $line . ', ' . __('rebuilt') : $line);
        }

        return $drift ? Command::FAILURE : Command::SUCCESS;
    }
}
