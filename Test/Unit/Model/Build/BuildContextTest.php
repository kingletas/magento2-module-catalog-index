<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use PHPUnit\Framework\TestCase;

class BuildContextTest extends TestCase
{
    public function testTheScopeAndMomentAreReadBackThroughTheGetters(): void
    {
        $now = new DateTimeImmutable('2030-01-02 03:04:05');
        $context = new BuildContext(2, 1, 77, $now, 'Europe/Lisbon');

        $this->assertSame(2, $context->getStoreId());
        $this->assertSame(1, $context->getWebsiteId());
        $this->assertSame(77, $context->getVersion());
        $this->assertSame($now, $context->getNow());
        $this->assertSame('Europe/Lisbon', $context->getTimezone());
    }

    public function testTheTimezoneDefaultsToUtc(): void
    {
        $this->assertSame('UTC', (new BuildContext(1, 1, 1, new DateTimeImmutable()))->getTimezone());
    }
}
