<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Drift;

use Kingletas\CatalogIndex\Model\Drift\DriftAlert;
use Kingletas\CatalogIndex\Model\Drift\DriftReport;
use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DriftAlertTest extends TestCase
{
    use ShippedConfig;

    /** @var array<string, mixed> */
    private array $state = [];

    private int $warnings = 0;

    /**
     * Drift under budget is the normal case and must say nothing, or the warning stops being read.
     */
    public function testDriftUnderBudgetIsSilent(): void
    {
        $this->assertFalse($this->alert(new FakeClock())->evaluate([new DriftReport(1, 100, [5])]));
        $this->assertSame(0, $this->warnings);
    }

    public function testARepeatWarnsOnlyWhenItDoublesOrTheQuietPeriodPasses(): void
    {
        $clock = new FakeClock();
        $alert = $this->alert($clock);
        $drifted = static fn (int $count): array => [new DriftReport(1, 100, range(1, $count))];

        $this->assertTrue($alert->evaluate($drifted(5)));
        $this->assertFalse($alert->evaluate($drifted(6)));
        $this->assertTrue($alert->evaluate($drifted(10)));
        $clock->advance('+7 hours');
        $this->assertTrue($alert->evaluate($drifted(10)));
        $this->assertSame(3, $this->warnings);
    }

    public function testRecoveryResetsTheBackoff(): void
    {
        $clock = new FakeClock();
        $alert = $this->alert($clock);

        $alert->evaluate([new DriftReport(1, 100, range(1, 5))]);
        $alert->evaluate([new DriftReport(1, 100, [])]);

        $this->assertTrue($alert->evaluate([new DriftReport(1, 100, range(1, 5))]));
    }

    private function alert(FakeClock $clock): DriftAlert
    {
        $state = $this->createMock(StateStorage::class);
        $state->method('get')->willReturnCallback(fn (string $key): ?array => $this->state[$key] ?? null);
        $state->method('set')->willReturnCallback(function (string $key, array $value): void {
            $this->state[$key] = $value;
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function (): void {
            $this->warnings++;
        });

        return new DriftAlert($this->config(), $state, $logger, $clock);
    }
}
