<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * How one document differs from the one it replaced.
 *
 * @api
 */
interface ChangeInterface
{
    public function getId(): int;

    public function isCreated(): bool;

    public function isDeleted(): bool;

    /**
     * @return string[]
     */
    public function getChangedGroups(): array;

    /**
     * @return int[]
     */
    public function getCategoriesBefore(): array;

    /**
     * @return int[]
     */
    public function getCategoriesAfter(): array;

    /**
     * @param string[] $groups
     */
    public function touchesAny(array $groups): bool;

    /**
     * @return int[] Categories the product joined or left.
     */
    public function movedCategories(): array;

    /**
     * @return int[]
     */
    public function allCategories(): array;

    public function isNothing(): bool;
}
