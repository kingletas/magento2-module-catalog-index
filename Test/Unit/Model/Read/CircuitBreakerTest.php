<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Read;

use Kingletas\CatalogIndex\Model\Read\CircuitBreaker;
use Kingletas\Foundation\Test\Support\ArrayCache;
use Kingletas\Foundation\Test\Support\FakeClock;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    use ShippedConfig;

    public function testItOpensAfterTheConfiguredFailuresAndClosesAfterTheCooldown(): void
    {
        $cache = new ArrayCache();
        $clock = new FakeClock();
        $config = $this->config(['read/breaker_failures' => '2', 'read/breaker_cooldown' => '30']);

        $first = new CircuitBreaker($cache, $config, $clock);
        $first->recordFailure();
        $this->assertFalse((new CircuitBreaker($cache, $config, $clock))->isOpen());
        $first->recordFailure();

        $this->assertTrue((new CircuitBreaker($cache, $config, $clock))->isOpen());
        $clock->advance('+31 seconds');
        $this->assertFalse((new CircuitBreaker($cache, $config, $clock))->isOpen());
    }

    /**
     * Every listing asks the breaker, so its state is read from the cache once per request, not once per call.
     */
    public function testTheStateIsReadOncePerRequest(): void
    {
        $cache = new ArrayCache();
        $breaker = new CircuitBreaker($cache, $this->config(), new FakeClock());

        $breaker->isOpen();
        $breaker->isOpen();

        $this->assertSame(1, $cache->loads);
    }

    public function testASuccessForgetsEarlierFailures(): void
    {
        $cache = new ArrayCache();
        $breaker = new CircuitBreaker($cache, $this->config(['read/breaker_failures' => '2']), new FakeClock());

        $breaker->recordFailure();
        $breaker->recordSuccess();
        $breaker->recordFailure();

        $this->assertFalse((new CircuitBreaker($cache, $this->config(), new FakeClock()))->isOpen());
    }
}
