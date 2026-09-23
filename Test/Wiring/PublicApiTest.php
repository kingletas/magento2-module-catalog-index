<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Wiring;

use Kingletas\CatalogIndex\Api\Data\BuildContextInterface;
use Kingletas\CatalogIndex\Api\Data\CategoryViewInterface;
use Kingletas\CatalogIndex\Api\Data\ChangeInterface;
use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;
use Kingletas\CatalogIndex\Api\Data\ConfigurableViewInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Api\Data\ProductViewInterface;
use Kingletas\CatalogIndex\Api\Data\ReadContextInterface;
use Kingletas\CatalogIndex\Api\Data\StockLevelInterface;
use Kingletas\CatalogIndex\Api\Data\WriteResultInterface;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Build\DocumentDraft;
use Kingletas\CatalogIndex\Model\Read\CategoryView;
use Kingletas\CatalogIndex\Model\Read\ConfigurableView;
use Kingletas\CatalogIndex\Model\Read\ProductView;
use Kingletas\CatalogIndex\Model\Read\ReadContext;
use Kingletas\CatalogIndex\Model\Stock\StockLevel;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Store\WriteResult;
use Kingletas\CatalogIndex\Model\Update\Change;
use Kingletas\CatalogIndex\Model\Update\ChangeSet;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;

/**
 * The public PHP surface: interfaces under Api, each implemented under Model and preferred in di.xml.
 */
class PublicApiTest extends TestCase
{
    private const array DATA_TYPES = [
        DocumentInterface::class => Document::class,
        DocumentDraftInterface::class => DocumentDraft::class,
        BuildContextInterface::class => BuildContext::class,
        ProductViewInterface::class => ProductView::class,
        ConfigurableViewInterface::class => ConfigurableView::class,
        CategoryViewInterface::class => CategoryView::class,
        ReadContextInterface::class => ReadContext::class,
        WriteResultInterface::class => WriteResult::class,
        StockLevelInterface::class => StockLevel::class,
        ChangeInterface::class => Change::class,
        ChangeSetInterface::class => ChangeSet::class,
    ];

    private const array INTERNAL_DIRS = ['Console', 'Cron', 'Exception', 'Model', 'Observer', 'Plugin', 'Queue'];

    public function testEveryDataTypeIsImplementedByItsModelClassAndPreferredInDiXml(): void
    {
        $preferences = $this->globalPreferences();
        $wrong = [];

        foreach (self::DATA_TYPES as $interface => $class) {
            if (!is_subclass_of($class, $interface)) {
                $wrong[] = $class . ' does not implement ' . $interface;
            }
            if (($preferences[$interface] ?? null) !== $class) {
                $wrong[] = 'etc/di.xml does not prefer ' . $class . ' for ' . $interface;
            }
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    public function testEveryApiFileIsMarkedApiAndNamesNoModelClass(): void
    {
        $wrong = [];

        foreach ($this->phpFiles('Api') as $file => $source) {
            if (!str_contains($source, '@api')) {
                $wrong[] = $file . ' is not marked @api';
            }
            if (str_contains($source, 'Kingletas\\CatalogIndex\\Model\\')) {
                $wrong[] = $file . ' names a class under Model';
            }
        }

        $this->assertSame([], $wrong, implode("\n", $wrong));
    }

    public function testNothingOutsideApiIsMarkedApi(): void
    {
        $marked = [];

        foreach (self::INTERNAL_DIRS as $dir) {
            foreach ($this->phpFiles($dir) as $file => $source) {
                if (preg_match('/^\s*\*\s*@api\b/m', $source) === 1) {
                    $marked[] = $file;
                }
            }
        }

        $this->assertSame([], $marked, 'Only Api/ is public: ' . implode(', ', $marked));
    }

    /**
     * @return array<string, string> Interface to the class etc/di.xml prefers for it.
     */
    private function globalPreferences(): array
    {
        $xml = new SimpleXMLElement((string) file_get_contents($this->moduleDir() . '/etc/di.xml'));
        $preferences = [];

        foreach ($xml->xpath('/config/preference') ?: [] as $preference) {
            $preferences[(string) $preference['for']] = (string) $preference['type'];
        }

        return $preferences;
    }

    /**
     * @return array<string, string> Path relative to the module, to the file's source.
     */
    private function phpFiles(string $dir): array
    {
        $root = $this->moduleDir() . '/' . $dir;
        $files = [];

        if (!is_dir($root)) {
            return [];
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen($this->moduleDir()) + 1);
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }

    private function moduleDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
