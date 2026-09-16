<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Store;

/**
 * What a bulk write did to each document.
 */
class WriteResult
{
    /**
     * @param string[] $written
     * @param string[] $stale Refused because a newer version was already stored.
     * @param array<string, string> $failed Document id to the reason the store gave.
     */
    public function __construct(
        public readonly array $written = [],
        public readonly array $stale = [],
        public readonly array $failed = []
    ) {
    }

    public function isClean(): bool
    {
        return $this->failed === [];
    }

    public function merge(WriteResult $other): WriteResult
    {
        return new WriteResult(
            array_merge($this->written, $other->written),
            array_merge($this->stale, $other->stale),
            $this->failed + $other->failed
        );
    }
}
