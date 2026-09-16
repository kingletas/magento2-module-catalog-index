<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Console\Command;

use Kingletas\CatalogIndex\Model\Status\StatusReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows whether the catalog index is built, current and actually being read.
 */
class StatusCommand extends Command
{
    private const string OPTION_HOURS = 'hours';

    public function __construct(
        private readonly StatusReporter $reporter,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription(
            (string) __('Show index builds, change-log backlog and how often pages fell back to the database.')
        )
            ->addOption(
                self::OPTION_HOURS,
                null,
                InputOption::VALUE_REQUIRED,
                (string) __('Hours of read counters to total.'),
                '24'
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = new Table($output);
        $summary->setHeaders([(string) __('setting'), (string) __('value')]);

        foreach ($this->reporter->summary() as $setting => $value) {
            $summary->addRow([$setting, $value]);
        }

        $summary->render();
        $this->table($output, $this->reporter->indexes(), (string) __('No store views are active.'));
        $this->table(
            $output,
            $this->reporter->reads(max(1, (int) $input->getOption(self::OPTION_HOURS))),
            (string) __('No page has read documents in that period.')
        );

        return Command::SUCCESS;
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function table(OutputInterface $output, array $rows, string $empty): void
    {
        if ($rows === []) {
            $output->writeln($empty);

            return;
        }

        $table = new Table($output);
        $table->setHeaders(array_keys($rows[0]));
        $table->setRows(array_map('array_values', $rows));
        $table->render();
    }
}
