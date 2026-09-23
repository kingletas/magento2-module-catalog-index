<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Api\Data\ChangeInterface;

/**
 * How one document differs from the one it replaced.
 */
class Change implements ChangeInterface
{
    /**
     * @param string[] $changedGroups
     * @param int[] $categoriesBefore
     * @param int[] $categoriesAfter
     */
    public function __construct(
        private readonly int $id,
        private readonly bool $created = false,
        private readonly bool $deleted = false,
        private readonly array $changedGroups = [],
        private readonly array $categoriesBefore = [],
        private readonly array $categoriesAfter = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function isCreated(): bool
    {
        return $this->created;
    }

    /**
     * @inheritDoc
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @inheritDoc
     */
    public function getChangedGroups(): array
    {
        return $this->changedGroups;
    }

    /**
     * @inheritDoc
     */
    public function getCategoriesBefore(): array
    {
        return $this->categoriesBefore;
    }

    /**
     * @inheritDoc
     */
    public function getCategoriesAfter(): array
    {
        return $this->categoriesAfter;
    }

    /**
     * @inheritDoc
     */
    public function touchesAny(array $groups): bool
    {
        return $this->created || $this->deleted || array_intersect($groups, $this->changedGroups) !== [];
    }

    /**
     * @inheritDoc
     */
    public function movedCategories(): array
    {
        return array_values(array_unique(array_merge(
            array_diff($this->categoriesBefore, $this->categoriesAfter),
            array_diff($this->categoriesAfter, $this->categoriesBefore)
        )));
    }

    /**
     * @inheritDoc
     */
    public function allCategories(): array
    {
        return array_values(array_unique(array_merge($this->categoriesBefore, $this->categoriesAfter)));
    }

    /**
     * @inheritDoc
     */
    public function isNothing(): bool
    {
        return !$this->created && !$this->deleted && $this->changedGroups === []
            && $this->movedCategories() === [];
    }
}
