<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Moves values saved under the old read/ group to the pages, features and breaker groups, in every scope.
 */
class SplitReadSettings implements DataPatchInterface
{
    /** @var array<string, string> Old path to new path. */
    public const array MOVED = [
        'kingletas_catalog_index/read/category_listing' => 'kingletas_catalog_index/pages/category_listing',
        'kingletas_catalog_index/read/search_listing' => 'kingletas_catalog_index/pages/search_listing',
        'kingletas_catalog_index/read/product_view' => 'kingletas_catalog_index/pages/product_view',
        'kingletas_catalog_index/read/category_view' => 'kingletas_catalog_index/pages/category_view',
        'kingletas_catalog_index/read/category_tree' => 'kingletas_catalog_index/pages/category_tree',
        'kingletas_catalog_index/read/linked_products' => 'kingletas_catalog_index/pages/linked_products',
        'kingletas_catalog_index/read/widget' => 'kingletas_catalog_index/pages/widget',
        'kingletas_catalog_index/read/graphql' => 'kingletas_catalog_index/pages/graphql',
        'kingletas_catalog_index/read/configurable_options' => 'kingletas_catalog_index/features/configurable_options',
        'kingletas_catalog_index/read/configurable_attributes'
            => 'kingletas_catalog_index/features/configurable_attributes',
        'kingletas_catalog_index/read/breaker_failures' => 'kingletas_catalog_index/breaker/failures',
        'kingletas_catalog_index/read/breaker_cooldown' => 'kingletas_catalog_index/breaker/cooldown',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $setup
    ) {
    }

    /**
     * A value already saved under the new path wins, so running this twice or after a manual change loses nothing.
     */
    public function apply(): self
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable('core_config_data');

        foreach (self::MOVED as $old => $new) {
            $taken = array_flip(array_map(
                static fn (array $row): string => $row['scope'] . '/' . $row['scope_id'],
                $this->rows($table, $new)
            ));
            $moved = array_values(array_filter(
                $this->rows($table, $old),
                static fn (array $row): bool => !isset($taken[$row['scope'] . '/' . $row['scope_id']])
            ));

            if ($moved !== []) {
                $connection->insertMultiple(
                    $table,
                    array_map(static fn (array $row): array => ['path' => $new] + $row, $moved)
                );
            }

            $connection->delete($table, ['path = ?' => $old]);
        }

        return $this;
    }

    /**
     * @return list<array{scope: string, scope_id: string, value: string}>
     */
    private function rows(string $table, string $path): array
    {
        $connection = $this->setup->getConnection();

        return array_values($connection->fetchAll(
            $connection->select()->from($table, ['scope', 'scope_id', 'value'])->where('path = ?', $path)
        ));
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
