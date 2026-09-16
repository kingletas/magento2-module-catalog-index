<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Console\Command;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Api\DocumentStoreInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\ProductDocumentBuilder;
use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\CatalogIndex\Model\Index\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\IndexNamer;
use Kingletas\CatalogIndex\Model\Index\ScopeResolver;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Shows, field by field, where one product's stored document differs from what the database would build now.
 */
class InspectCommand extends Command
{
    private const string ARGUMENT_SKU = 'sku';
    private const string OPTION_STORE = 'store-id';

    public function __construct(
        private readonly ProductResource $productResource,
        private readonly ProductDocumentBuilder $builder,
        private readonly DocumentStoreInterface $store,
        private readonly IndexNamer $namer,
        private readonly ScopeResolver $scopes,
        private readonly VersionSource $versions,
        private readonly ClockInterface $clock,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setDescription((string) __('Compare one product\'s stored document with a fresh build.'))
            ->addArgument(self::ARGUMENT_SKU, InputArgument::REQUIRED, (string) __('The product SKU.'))
            ->addOption(self::OPTION_STORE, 's', InputOption::VALUE_REQUIRED, (string) __('Store view id.'), '1');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sku = (string) $input->getArgument(self::ARGUMENT_SKU);
        $productId = (int) $this->productResource->getIdBySku($sku);
        $storeId = (int) $input->getOption(self::OPTION_STORE);

        if ($productId <= 0) {
            $output->writeln('<error>' . __('No product has the SKU "%1".', $sku) . '</error>');

            return Command::INVALID;
        }

        $context = new BuildContext(
            $storeId,
            $this->scopes->websiteIdOf($storeId),
            $this->versions->next(),
            $this->clock->now()
        );
        $built = $this->builder->build([$productId], $context)->documents[0] ?? null;
        $alias = $this->namer->alias(IndexFamily::Product, $storeId);
        $fetched = $this->store->fetch([$alias => [(string) $productId]]);
        $stored = array_values($fetched[$alias] ?? [])[0] ?? null;
        $builtSource = $built === null ? [] : $built->getSource();
        $storedSource = $stored === null ? [] : $stored->getSource();
        $differences = $this->differences($builtSource, $storedSource);

        $output->writeln((string) __(
            'product %1 in store %2: built %3, stored %4',
            $productId,
            $storeId,
            $built === null ? __('nothing (excluded)') : __('a document'),
            $stored === null ? __('nothing') : __('version %1', $stored->getVersion())
        ));

        foreach ($differences as $field) {
            $output->writeln('  ' . __('differs: %1', $field));
        }

        return $differences === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param array<string, mixed> $built
     * @param array<string, mixed> $stored
     * @return string[]
     */
    private function differences(array $built, array $stored): array
    {
        $fields = array_unique(array_merge(array_keys($built), array_keys($stored)));
        sort($fields);

        return array_values(array_filter(
            $fields,
            static fn (string $field): bool => ($built[$field] ?? null) !== ($stored[$field] ?? null)
        ));
    }
}
