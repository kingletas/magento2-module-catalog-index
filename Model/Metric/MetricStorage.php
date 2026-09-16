<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Metric;

use Kingletas\Foundation\Api\ClockInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Hourly read counters kept in the application cache, approximate under concurrency and never written to the database.
 */
class MetricStorage
{
    private const string PREFIX = 'kingletas_catalog_index_metric_';
    private const int LIFETIME = 172800;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @param array<string, int> $served Page to documents served.
     * @param array<string, array<string, int>> $fallbacks Page to reason to reads that went to the database.
     */
    public function add(array $served, array $fallbacks): void
    {
        if ($served === [] && $fallbacks === []) {
            return;
        }

        $key = self::PREFIX . $this->clock->now()->format('YmdH');
        $bucket = $this->load($key);

        foreach ($served as $page => $count) {
            $bucket[$page]['served'] = (int) ($bucket[$page]['served'] ?? 0) + $count;
        }

        foreach ($fallbacks as $page => $reasons) {
            foreach ($reasons as $reason => $count) {
                $bucket[$page]['fallback'][$reason] = (int) ($bucket[$page]['fallback'][$reason] ?? 0) + $count;
            }
        }

        $this->cache->save((string) $this->json->serialize($bucket), $key, [], self::LIFETIME);
    }

    /**
     * @return array<string, array{served: int, fallback: array<string, int>}> Totals over the last hours.
     */
    public function totals(int $hours): array
    {
        $totals = [];
        $now = $this->clock->now();

        $hours = max(1, $hours);

        for ($offset = 0; $offset < $hours; $offset++) {
            $bucket = $this->load(self::PREFIX . $now->modify(sprintf('-%d hour', $offset))->format('YmdH'));

            foreach ($bucket as $page => $counts) {
                $totals[$page]['served'] = ($totals[$page]['served'] ?? 0) + (int) ($counts['served'] ?? 0);
                $totals[$page]['fallback'] ??= [];

                foreach ((array) ($counts['fallback'] ?? []) as $reason => $count) {
                    $totals[$page]['fallback'][$reason] = ($totals[$page]['fallback'][$reason] ?? 0) + (int) $count;
                }
            }
        }

        ksort($totals);

        return $totals;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(string $key): array
    {
        $raw = $this->cache->load($key);
        $decoded = is_string($raw) && $raw !== '' ? $this->json->unserialize($raw) : [];

        return is_array($decoded) ? $decoded : [];
    }
}
