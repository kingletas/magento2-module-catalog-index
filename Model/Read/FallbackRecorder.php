<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Read;

use Kingletas\CatalogIndex\Api\Data\PageType;
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
    public const string REASON_PREEMPTED = 'preempted';

    /** Counted beside the pages, because Magento asks for a configurable's attributes on any of them. */
    public const string CONFIGURABLE_ATTRIBUTES = 'configurable_attributes';

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
        $this->countServed($page->value, $count);
    }

    public function fellBack(PageType $page, string $reason, int $count = 1): void
    {
        $this->countFallback($page->value, $reason, $count);
    }

    /**
     * A configurable's attribute collection was built from its document when Magento asked for it.
     */
    public function attributesServed(): void
    {
        $this->countServed(self::CONFIGURABLE_ATTRIBUTES, 1);
    }

    /**
     * A configurable had a document, and a plugin sorted before this module's answered its attributes first.
     */
    public function attributesPreempted(): void
    {
        $this->countFallback(self::CONFIGURABLE_ATTRIBUTES, self::REASON_PREEMPTED, 1);
    }

    public function flush(): void
    {
        $this->storage->add($this->served, $this->fallbacks);
        $this->served = [];
        $this->fallbacks = [];
    }

    private function countServed(string $surface, int $count): void
    {
        $this->served[$surface] = ($this->served[$surface] ?? 0) + $count;
    }

    private function countFallback(string $surface, string $reason, int $count): void
    {
        $this->fallbacks[$surface][$reason] = ($this->fallbacks[$surface][$reason] ?? 0) + $count;
    }
}
