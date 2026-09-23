<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Setup\Patch\Data;

use Kingletas\CatalogIndex\Setup\Patch\Data\SplitReadSettings;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class SplitReadSettingsTest extends TestCase
{
    /** @var array<string, list<array{scope: string, scope_id: string, value: string}>> Path to its saved rows. */
    private array $saved = [];

    /** @var list<list<array<string, string>>> Rows each insert carried. */
    private array $inserted = [];

    /** @var list<string> Old paths deleted. */
    private array $deleted = [];

    public function testASavedValueMovesToItsNewPathInTheSameScope(): void
    {
        $this->saved['kingletas_catalog_index/read/widget'] = [
            ['scope' => 'stores', 'scope_id' => '2', 'value' => '1'],
        ];
        $this->saved['kingletas_catalog_index/read/breaker_failures'] = [
            ['scope' => 'default', 'scope_id' => '0', 'value' => '8'],
        ];

        $this->patch()->apply();

        $this->assertSame([
            [[
                'path' => 'kingletas_catalog_index/pages/widget',
                'scope' => 'stores',
                'scope_id' => '2',
                'value' => '1',
            ]],
            [[
                'path' => 'kingletas_catalog_index/breaker/failures',
                'scope' => 'default',
                'scope_id' => '0',
                'value' => '8',
            ]],
        ], $this->inserted);
        $this->assertContains('kingletas_catalog_index/read/widget', $this->deleted);
        $this->assertContains('kingletas_catalog_index/read/breaker_failures', $this->deleted);
    }

    /**
     * A value already saved under the new path in that scope is kept, and the old one is still removed.
     */
    public function testAValueAlreadyAtTheNewPathWins(): void
    {
        $this->saved['kingletas_catalog_index/read/widget'] = [
            ['scope' => 'stores', 'scope_id' => '2', 'value' => '0'],
            ['scope' => 'default', 'scope_id' => '0', 'value' => '1'],
        ];
        $this->saved['kingletas_catalog_index/pages/widget'] = [
            ['scope' => 'stores', 'scope_id' => '2', 'value' => '1'],
        ];

        $this->patch()->apply();

        $this->assertSame(
            [[[
                'path' => 'kingletas_catalog_index/pages/widget',
                'scope' => 'default',
                'scope_id' => '0',
                'value' => '1',
            ]]],
            $this->inserted
        );
        $this->assertContains('kingletas_catalog_index/read/widget', $this->deleted);
    }

    public function testNothingSavedMeansNothingWritten(): void
    {
        $this->patch()->apply();

        $this->assertSame([], $this->inserted);
    }

    /**
     * Every new path the patch writes to must be one config.xml ships a default for, or the value is orphaned.
     */
    public function testEveryNewPathIsOneTheModuleDeclares(): void
    {
        $defaults = simplexml_load_file(dirname(__DIR__, 5) . '/etc/config.xml');
        $this->assertInstanceOf(SimpleXMLElement::class, $defaults);

        foreach (SplitReadSettings::MOVED as $new) {
            $this->assertNotEmpty($defaults->xpath('/config/default/' . $new) ?: [], $new . ' has no default');
        }

        $this->assertCount(12, SplitReadSettings::MOVED);
    }

    private function patch(): SplitReadSettings
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnCallback(function (): Select {
            $path = '';
            $select = $this->createMock(Select::class);
            $select->method('from')->willReturnSelf();
            $select->method('where')->willReturnCallback(function (string $cond, mixed $value) use ($select, &$path) {
                $path = (string) $value;

                return $select;
            });
            $select->method('__toString')->willReturnCallback(static function () use (&$path): string {
                return $path;
            });

            return $select;
        });
        $connection->method('fetchAll')->willReturnCallback(
            fn (Select $select): array => $this->saved[(string) $select] ?? []
        );
        $connection->method('insertMultiple')->willReturnCallback(function (string $table, array $rows): int {
            $this->inserted[] = $rows;

            return count($rows);
        });
        $connection->method('delete')->willReturnCallback(function (string $table, array $where): int {
            $this->deleted[] = (string) reset($where);

            return 1;
        });

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnArgument(0);

        return new SplitReadSettings($setup);
    }
}
