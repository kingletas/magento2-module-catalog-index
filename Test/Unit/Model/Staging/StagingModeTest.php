<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Staging;

use Kingletas\CatalogIndex\Model\Build\LinkField;
use Kingletas\CatalogIndex\Model\Staging\StagingMode;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Magento\Framework\Module\Manager as ModuleManager;
use PHPUnit\Framework\TestCase;

class StagingModeTest extends TestCase
{
    use ShippedConfig;

    public function testAutomaticFollowsWhatIsInstalled(): void
    {
        $this->assertTrue($this->mode('auto', moduleEnabled: true, staged: true)->isActive());
        $this->assertFalse($this->mode('auto', moduleEnabled: false, staged: true)->isActive());
        $this->assertFalse($this->mode('auto', moduleEnabled: true, staged: false)->isActive());
    }

    /**
     * Forcing it on where tables have no staged versions would only ever scan columns that are not there.
     */
    public function testForcedModesStillRequireStagedTablesToTurnOn(): void
    {
        $this->assertTrue($this->mode('enabled', moduleEnabled: false, staged: true)->isActive());
        $this->assertFalse($this->mode('enabled', moduleEnabled: false, staged: false)->isActive());
        $this->assertFalse($this->mode('disabled', moduleEnabled: true, staged: true)->isActive());
    }

    private function mode(string $setting, bool $moduleEnabled, bool $staged): StagingMode
    {
        $modules = $this->createMock(ModuleManager::class);
        $modules->method('isEnabled')->willReturn($moduleEnabled);
        $link = $this->createMock(LinkField::class);
        $link->method('isStaged')->willReturn($staged);

        return new StagingMode($this->config(['staging/mode' => $setting]), $modules, $link);
    }
}
