<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Rebuild;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Rebuild\ChangelogReader;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\StateInterface;
use Magento\Framework\Mview\ViewInterface;
use Magento\Framework\Mview\ViewInterfaceFactory;
use PHPUnit\Framework\TestCase;

class ChangelogReaderTest extends TestCase
{
    public function testAScheduledFamilyReportsItsVersionBacklogAndIds(): void
    {
        $reader = $this->reader(enabled: true);

        $this->assertSame(40, $reader->currentVersion(IndexFamily::Product));
        $this->assertSame(15, $reader->backlog(IndexFamily::Product));
        $this->assertSame([3, 4], $reader->idsBetween(IndexFamily::Product, 30, 40));
        $this->assertSame([], $reader->idsBetween(IndexFamily::Product, 40, 40));
    }

    /**
     * Realtime mode keeps no change log, so a rebuild has nothing to replay and must not pretend otherwise.
     */
    public function testAnUnscheduledOrUnknownFamilyHasNoVersion(): void
    {
        $this->assertNull($this->reader(enabled: false)->currentVersion(IndexFamily::Product));
        $this->assertNull($this->reader(enabled: false)->backlog(IndexFamily::Product));
        $this->assertNull($this->reader(enabled: true)->currentVersion(IndexFamily::Stock));
    }

    private function reader(bool $enabled): ChangelogReader
    {
        $changelog = $this->createMock(ChangelogInterface::class);
        $changelog->method('getVersion')->willReturn(40);
        $changelog->method('getList')->willReturn(['3', '4', '4']);
        $state = $this->createMock(StateInterface::class);
        $state->method('getVersionId')->willReturn(25);
        $view = $this->createMock(ViewInterface::class);
        $view->method('load')->willReturnSelf();
        $view->method('isEnabled')->willReturn($enabled);
        $view->method('getChangelog')->willReturn($changelog);
        $view->method('getState')->willReturn($state);
        $factory = $this->createMock(ViewInterfaceFactory::class);
        $factory->method('create')->willReturn($view);

        return new ChangelogReader($factory, ['product' => 'kingletas_catalog_index_product']);
    }
}
