<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Metric;

use Kingletas\CatalogIndex\Model\Metric\MetricStorage;
use Kingletas\CatalogIndex\Model\Read\FallbackRecorder;
use Kingletas\CatalogIndex\Model\Read\PageType;
use Kingletas\Foundation\Test\Support\ArrayCache;
use Kingletas\Foundation\Test\Support\FakeClock;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class MetricStorageTest extends TestCase
{
    public function testCountersAddUpAcrossRequestsAndHours(): void
    {
        $cache = new ArrayCache();
        $clock = new FakeClock();
        $storage = new MetricStorage($cache, new Json(), $clock);
        $recorder = new FallbackRecorder($storage);

        $recorder->served(PageType::CategoryListing, 20);
        $recorder->fellBack(PageType::CategoryListing, FallbackRecorder::REASON_MISSING, 2);
        $recorder->flush();
        $clock->advance('+1 hour');
        $recorder->served(PageType::CategoryListing, 10);
        $recorder->fellBack(PageType::CategoryListing, FallbackRecorder::REASON_MISSING);
        $recorder->flush();

        $this->assertSame(
            ['category_listing' => ['served' => 30, 'fallback' => ['missing_document' => 3]]],
            $storage->totals(2)
        );
        $this->assertSame(
            ['served' => 10, 'fallback' => ['missing_document' => 1]],
            $storage->totals(1)['category_listing']
        );
    }

    /**
     * A request that read nothing writes nothing, so a quiet page costs the cache nothing.
     */
    public function testAnEmptyFlushWritesNothing(): void
    {
        $cache = new ArrayCache();

        (new FallbackRecorder(new MetricStorage($cache, new Json(), new FakeClock())))->flush();

        $this->assertSame([], $cache->entries);
    }
}
