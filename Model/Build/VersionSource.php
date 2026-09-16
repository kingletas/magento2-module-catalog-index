<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;
use Kingletas\Foundation\Api\ClockInterface;

/**
 * Versions taken from the clock before any read, so a later build of the same entity always outranks an earlier one.
 */
class VersionSource
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {
    }

    public function next(): int
    {
        return $this->of($this->clock->now());
    }

    public function of(DateTimeImmutable $moment): int
    {
        return (int) $moment->format('Uu');
    }
}
