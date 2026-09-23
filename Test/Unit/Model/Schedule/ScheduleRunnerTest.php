<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Schedule;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Model\Schedule\ScheduledRefresh;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleRunner;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleStorage;
use Kingletas\CatalogIndex\Model\Staging\ScheduledVersionScanner;
use Kingletas\CatalogIndex\Model\Staging\StagingMode;
use Kingletas\CatalogIndex\Model\Update\RefreshPublisher;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class ScheduleRunnerTest extends TestCase
{
    use ShippedConfig;

    /** @var array<int, array{0: string, 1: int[]}> */
    private array $published = [];

    /** @var int[] */
    private array $removed = [];

    /** @var array<string, mixed> */
    private array $state = [];

    public function testDueDocumentsAreSentByFamilyAndThenForgotten(): void
    {
        $sent = $this->runner(stagingActive: false)->run();

        $this->assertSame(2, $sent);
        $this->assertSame([['product', [5, 6]]], $this->published);
        $this->assertSame([1, 2], $this->removed);
    }

    /**
     * A staged version starting fires no trigger, so the runner is the only thing that notices it.
     */
    public function testStagedVersionsThatStartedSinceTheLastRunAreRefreshed(): void
    {
        $this->state['watermark:staging'] = ['at' => 1789473540];

        $this->runner(stagingActive: true, due: [])->run();

        $this->assertSame([['product', [7]], ['price', [7]], ['category', [12]]], $this->published);
        $this->assertSame(['at' => 1789473600], $this->state['watermark:staging']);
    }

    public function testNothingRunsWhileDisabled(): void
    {
        $this->assertSame(0, $this->runner(stagingActive: true, config: ['general/enabled' => '0'])->run());
    }

    /**
     * @param ScheduledRefresh[]|null $due
     * @param array<string, string> $config
     */
    private function runner(bool $stagingActive, ?array $due = null, array $config = []): ScheduleRunner
    {
        $storage = $this->createMock(ScheduleStorage::class);
        $storage->method('due')->willReturn($due ?? [
            new ScheduledRefresh(1, IndexFamily::Product, 5, 1, 100),
            new ScheduledRefresh(2, IndexFamily::Product, 6, 1, 100),
        ]);
        $storage->method('remove')->willReturnCallback(function (array $ids): void {
            $this->removed = array_merge($this->removed, $ids);
        });
        $staging = $this->createMock(StagingMode::class);
        $staging->method('isActive')->willReturn($stagingActive);
        $scanner = $this->createMock(ScheduledVersionScanner::class);
        $scanner->method('products')->willReturn([7]);
        $scanner->method('categories')->willReturn([12]);
        $state = $this->createMock(StateStorage::class);
        $state->method('get')->willReturnCallback(fn (string $key): ?array => $this->state[$key] ?? null);
        $state->method('set')->willReturnCallback(function (string $key, array $value): void {
            $this->state[$key] = $value;
        });
        $publisher = $this->createMock(RefreshPublisher::class);
        $publisher->method('publish')->willReturnCallback(function (IndexFamily $family, array $ids): void {
            $this->published[] = [$family->value, $ids];
        });

        return new ScheduleRunner(
            $this->config($config),
            $storage,
            $staging,
            $scanner,
            $state,
            $publisher,
            new FakeClock()
        );
    }
}
