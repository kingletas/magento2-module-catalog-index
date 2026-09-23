<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Stock;

use Kingletas\CatalogIndex\Model\Stock\MsiStockReader;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use PHPUnit\Framework\TestCase;

class MsiStockReaderTest extends TestCase
{
    use StubbedDatabase;

    /**
     * An open order holds stock the index still counts, so the shopper sees what is left after reservations.
     */
    public function testReservationsAndTheMinimumQuantityAreSubtracted(): void
    {
        $this->answers = [
            'catalog_product_entity' => ['SKU-5' => '5', 'SKU-6' => '6'],
            'inventory_stock_sales_channel' => '2',
            'inventory_stock_2' => [
                ['sku' => 'SKU-5', 'quantity' => '3', 'is_salable' => '1'],
                ['sku' => 'SKU-6', 'quantity' => '10', 'is_salable' => '1'],
            ],
            'inventory_reservation' => ['SKU-5' => '-3'],
            'cataloginventory_stock_item' => [
                [
                    'product_id' => '5',
                    'min_qty' => '0',
                    'use_config_min_qty' => '1',
                    'manage_stock' => '1',
                    'use_config_manage_stock' => '1',
                    'backorders' => '0',
                    'use_config_backorders' => '1',
                ],
                [
                    'product_id' => '6',
                    'min_qty' => '2',
                    'use_config_min_qty' => '0',
                    'manage_stock' => '1',
                    'use_config_manage_stock' => '1',
                    'backorders' => '0',
                    'use_config_backorders' => '1',
                ],
            ],
        ];

        $levels = $this->reader(true)->read([5, 6], 1);

        $this->assertFalse($levels[5]->isSalable());
        $this->assertSame(0.0, $levels[5]->getSalableQuantity());
        $this->assertTrue($levels[6]->isSalable());
        $this->assertSame(8.0, $levels[6]->getSalableQuantity());
        $this->assertSame(2, $levels[6]->getStockId());
    }

    public function testBackordersKeepAProductBuyableAtZero(): void
    {
        $this->answers = [
            'catalog_product_entity' => ['SKU-5' => '5'],
            'inventory_stock_1' => [['sku' => 'SKU-5', 'quantity' => '0', 'is_salable' => '1']],
            'cataloginventory_stock_item' => [
                [
                    'product_id' => '5',
                    'min_qty' => '0',
                    'use_config_min_qty' => '1',
                    'manage_stock' => '1',
                    'use_config_manage_stock' => '1',
                    'backorders' => '1',
                    'use_config_backorders' => '0',
                ],
            ],
        ];

        $this->assertTrue($this->reader(true)->read([5], 1)[5]->isSalable());
    }

    public function testItAppliesOnlyWhereInventoryIsEnabledAndItsTablesExist(): void
    {
        $this->assertTrue($this->reader(true)->isApplicable());
        $this->assertFalse($this->reader(false)->isApplicable());
    }

    private function reader(bool $inventoryEnabled): MsiStockReader
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isEnabled')->willReturn($inventoryEnabled);
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path): string => match ($path) {
            'cataloginventory/item_options/manage_stock' => '1',
            default => '0',
        });

        return new MsiStockReader($this->resourceConnection(), $modules, $scopeConfig);
    }
}
