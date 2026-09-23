<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * What a bulk write did to each document.
 *
 * @api
 */
interface WriteResultInterface
{
    /**
     * @return string[]
     */
    public function getWritten(): array;

    /**
     * @return string[] Refused because a newer version was already stored.
     */
    public function getStale(): array;

    /**
     * @return array<string, string> Document id to the reason the store gave.
     */
    public function getFailed(): array;

    public function isClean(): bool;

    /**
     * Returns a new result holding both; neither operand changes.
     */
    public function merge(WriteResultInterface $other): WriteResultInterface;
}
