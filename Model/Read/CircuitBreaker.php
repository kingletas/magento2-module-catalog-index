<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\Foundation\Api\ClockInterface;
use Kingletas\CatalogIndex\Model\Config;
use Magento\Framework\App\CacheInterface;

/**
 * Stops pages asking the document store after repeated failures, so an outage costs one timeout per cooldown.
 */
class CircuitBreaker
{
    private const string OPEN_UNTIL = 'kingletas_catalog_index_breaker_open_until';
    private const string FAILURES = 'kingletas_catalog_index_breaker_failures';

    private ?int $openUntil = null;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Config $config,
        private readonly ClockInterface $clock
    ) {
    }

    public function isOpen(): bool
    {
        $this->openUntil ??= (int) $this->cache->load(self::OPEN_UNTIL);

        return $this->openUntil > $this->clock->now()->getTimestamp();
    }

    public function recordFailure(): void
    {
        $failures = (int) $this->cache->load(self::FAILURES) + 1;
        $cooldown = $this->config->getBreakerCooldownSeconds();

        if ($failures < $this->config->getBreakerFailures()) {
            $this->cache->save((string) $failures, self::FAILURES, [], $cooldown);

            return;
        }

        $this->openUntil = $this->clock->now()->getTimestamp() + $cooldown;
        $this->cache->save((string) $this->openUntil, self::OPEN_UNTIL, [], $cooldown);
        $this->cache->remove(self::FAILURES);
    }

    public function recordSuccess(): void
    {
        if ($this->cache->load(self::FAILURES) !== false) {
            $this->cache->remove(self::FAILURES);
        }
    }
}
