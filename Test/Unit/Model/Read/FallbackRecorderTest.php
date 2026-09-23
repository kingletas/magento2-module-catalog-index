<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\Foundation\Test\Support\ArrayCache;
use Kingletas\Foundation\Test\Support\FakeClock;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class FallbackRecorderTest extends TestCase
{
    /**
     * Status reads its rows from the stored totals, so a pre-empted configurable shows up there beside the pages.
     */
    public function testConfigurableAttributesAreCountedAsTheirOwnRow(): void
    {
        $storage = new MetricStorage(new ArrayCache(), new Json(), new FakeClock());
        $recorder = new FallbackRecorder($storage);

        $recorder->attributesServed();
        $recorder->attributesServed();
        $recorder->attributesPreempted();
        $recorder->flush();

        $this->assertSame(
            ['configurable_attributes' => ['served' => 2, 'fallback' => ['preempted' => 1]]],
            $storage->totals(1)
        );
    }

    public function testAFlushStartsTheNextRequestFromNothing(): void
    {
        $storage = new MetricStorage(new ArrayCache(), new Json(), new FakeClock());
        $recorder = new FallbackRecorder($storage);

        $recorder->attributesPreempted();
        $recorder->flush();
        $recorder->flush();

        $this->assertSame(['preempted' => 1], $storage->totals(1)['configurable_attributes']['fallback']);
    }
}
