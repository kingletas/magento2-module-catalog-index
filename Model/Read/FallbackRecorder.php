<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Model\Metric\MetricStorage;

/**
 * Counts, per request, what was served from documents and what fell back to the database and why.
 */
class FallbackRecorder
{
    public const string REASON_MISSING = 'missing_document';
    public const string REASON_STORE_ERROR = 'store_error';
    public const string REASON_BREAKER_OPEN = 'breaker_open';
    public const string REASON_UNSUPPORTED = 'unsupported_product';

    /** @var array<string, int> */
    private array $served = [];

    /** @var array<string, array<string, int>> */
    private array $fallbacks = [];

    public function __construct(
        private readonly MetricStorage $storage
    ) {
    }

    public function served(PageType $page, int $count = 1): void
    {
        $this->served[$page->value] = ($this->served[$page->value] ?? 0) + $count;
    }

    public function fellBack(PageType $page, string $reason, int $count = 1): void
    {
        $this->fallbacks[$page->value][$reason] = ($this->fallbacks[$page->value][$reason] ?? 0) + $count;
    }

    public function flush(): void
    {
        $this->storage->add($this->served, $this->fallbacks);
        $this->served = [];
        $this->fallbacks = [];
    }
}
