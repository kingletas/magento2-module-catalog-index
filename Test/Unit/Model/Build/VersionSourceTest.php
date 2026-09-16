<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\VersionSource;
use Kingletas\Foundation\Test\Support\FakeClock;
use PHPUnit\Framework\TestCase;

class VersionSourceTest extends TestCase
{
    /**
     * A build that reads later must outrank one that read earlier, or a slow worker overwrites fresh data.
     */
    public function testALaterMomentAlwaysGivesAHigherVersion(): void
    {
        $clock = new FakeClock('2026-09-15 12:00:00.000001');
        $versions = new VersionSource($clock);
        $first = $versions->next();
        $clock->advance('+1 microsecond');

        $this->assertGreaterThan($first, $versions->next());
        $this->assertSame(1789473600000001, $first);
    }
}
