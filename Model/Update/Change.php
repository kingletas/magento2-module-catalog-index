<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

/**
 * How one document differs from the one it replaced.
 */
class Change
{
    /**
     * @param string[] $changedGroups
     * @param int[] $categoriesBefore
     * @param int[] $categoriesAfter
     */
    public function __construct(
        public readonly int $id,
        public readonly bool $created = false,
        public readonly bool $deleted = false,
        public readonly array $changedGroups = [],
        public readonly array $categoriesBefore = [],
        public readonly array $categoriesAfter = []
    ) {
    }

    /**
     * @param string[] $groups
     */
    public function touchesAny(array $groups): bool
    {
        return $this->created || $this->deleted || array_intersect($groups, $this->changedGroups) !== [];
    }

    /**
     * @return int[] Categories the product joined or left.
     */
    public function movedCategories(): array
    {
        return array_values(array_unique(array_merge(
            array_diff($this->categoriesBefore, $this->categoriesAfter),
            array_diff($this->categoriesAfter, $this->categoriesBefore)
        )));
    }

    /**
     * @return int[]
     */
    public function allCategories(): array
    {
        return array_values(array_unique(array_merge($this->categoriesBefore, $this->categoriesAfter)));
    }

    public function isNothing(): bool
    {
        return !$this->created && !$this->deleted && $this->changedGroups === []
            && $this->movedCategories() === [];
    }
}
