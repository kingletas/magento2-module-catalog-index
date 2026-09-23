<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Store;

use Kingletas\CatalogIndex\Api\Data\WriteResultInterface;

/**
 * What a bulk write did to each document.
 */
class WriteResult implements WriteResultInterface
{
    /**
     * @param string[] $written
     * @param string[] $stale Refused because a newer version was already stored.
     * @param array<string, string> $failed Document id to the reason the store gave.
     */
    public function __construct(
        private readonly array $written = [],
        private readonly array $stale = [],
        private readonly array $failed = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getWritten(): array
    {
        return $this->written;
    }

    /**
     * @inheritDoc
     */
    public function getStale(): array
    {
        return $this->stale;
    }

    /**
     * @inheritDoc
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * @inheritDoc
     */
    public function isClean(): bool
    {
        return $this->failed === [];
    }

    /**
     * @inheritDoc
     */
    public function merge(WriteResultInterface $other): WriteResultInterface
    {
        return new WriteResult(
            array_merge($this->written, $other->getWritten()),
            array_merge($this->stale, $other->getStale()),
            $this->failed + $other->getFailed()
        );
    }
}
