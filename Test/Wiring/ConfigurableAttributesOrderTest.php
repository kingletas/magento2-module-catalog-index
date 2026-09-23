<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Wiring;

use PHPUnit\Framework\TestCase;

/**
 * The catalog batch module answers the same Magento call, so the order the two plugins run in is declared.
 */
class ConfigurableAttributesOrderTest extends TestCase
{
    /** The order both modules' READMEs document: this module first, then the catalog batch module. */
    private const array EXPECTED = ['index' => 10, 'batch' => 20];

    private const string INDEX_PLUGIN = 'kingletas_catalog_index_seed_configurable_attributes';
    private const string BATCH_PLUGIN = 'kingletas_catalog_batch_seed_attributes_on_demand';

    public function testThisModuleDeclaresTheDocumentedSortOrder(): void
    {
        $this->assertSame(
            self::EXPECTED['index'],
            $this->sortOrder($this->moduleDir() . '/etc/frontend/di.xml', self::INDEX_PLUGIN)
        );
    }

    /**
     * Read from the batch module's own di.xml when it is checked out beside this one, else from its documented value.
     */
    public function testThisModuleAnswersBeforeTheBatchModule(): void
    {
        $batchFile = dirname($this->moduleDir()) . '/module-catalog-batch/etc/frontend/di.xml';
        $batch = is_file($batchFile) ? $this->sortOrder($batchFile, self::BATCH_PLUGIN) : self::EXPECTED['batch'];

        $this->assertNotNull($batch, 'The catalog batch plugin declares no sortOrder.');
        $this->assertLessThan(
            $batch,
            (int) $this->sortOrder($this->moduleDir() . '/etc/frontend/di.xml', self::INDEX_PLUGIN)
        );
    }

    private function sortOrder(string $file, string $plugin): ?int
    {
        $config = simplexml_load_file($file);
        $this->assertNotFalse($config, $file . ' does not parse');

        $found = $config->xpath(sprintf('//plugin[@name="%s"]/@sortOrder', $plugin)) ?: [];

        return $found === [] ? null : (int) $found[0];
    }

    private function moduleDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
