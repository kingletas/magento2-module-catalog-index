<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Cron;

use Kingletas\CatalogIndex\Cron\FlushParkedPurges;
use Kingletas\CatalogIndex\Cron\RunSchedule;
use Kingletas\CatalogIndex\Cron\SweepReservations;
use Kingletas\CatalogIndex\Cron\VerifyDrift;
use Kingletas\CatalogIndex\Model\Cache\ParkedPurgeFlusher;
use Kingletas\CatalogIndex\Model\Drift\DriftAlert;
use Kingletas\CatalogIndex\Model\Drift\DriftReport;
use Kingletas\CatalogIndex\Model\Drift\DriftVerifier;
use Kingletas\CatalogIndex\Model\Schedule\ScheduleRunner;
use Kingletas\CatalogIndex\Model\Stock\ReservationSweeper;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use Kingletas\Foundation\Model\Lock\LockRunner;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CronJobsTest extends TestCase
{
    use ShippedConfig;

    public function testEachJobRunsTheWorkItNames(): void
    {
        $runner = $this->createMock(ScheduleRunner::class);
        $runner->expects($this->once())->method('run');
        $sweeper = $this->createMock(ReservationSweeper::class);
        $sweeper->expects($this->once())->method('sweep');
        $reports = [new DriftReport(1, 10)];
        $verifier = $this->createMock(DriftVerifier::class);
        $verifier->method('verify')->willReturn($reports);
        $alert = $this->createMock(DriftAlert::class);
        $alert->expects($this->once())->method('evaluate')->with($reports);

        (new RunSchedule($runner))->execute();
        (new SweepReservations($sweeper))->execute();
        (new VerifyDrift($verifier, $alert))->execute();
    }

    public function testParkedPurgesAreFlushedUnderThePurgeLock(): void
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects($this->once())->method('lock')->with('purge', 5)->willReturn(true);
        $locks->expects($this->once())->method('unlock')->with('purge');
        $flusher = $this->createMock(ParkedPurgeFlusher::class);
        $flusher->expects($this->once())->method('flushHeld');

        (new FlushParkedPurges($flusher, new LockRunner($locks, new NullLogger()), $this->config(), 'purge', 5))
            ->execute();
    }

    public function testNothingIsFlushedWhilePurgingIsOff(): void
    {
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->expects($this->never())->method('lock');
        $flusher = $this->createMock(ParkedPurgeFlusher::class);
        $flusher->expects($this->never())->method('flushHeld');

        (new FlushParkedPurges(
            $flusher,
            new LockRunner($locks, new NullLogger()),
            $this->config(['purge/enabled' => '0'])
        ))->execute();
    }
}
