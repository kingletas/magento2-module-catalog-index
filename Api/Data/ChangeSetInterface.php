<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * Every change one refresh made, plus how the store answered.
 *
 * @api
 */
interface ChangeSetInterface
{
    /**
     * Keeps one change per document and scope, and drops a change that changed nothing.
     */
    public function add(ChangeInterface $change, string $scope = ''): void;

    /**
     * @param array<string, string> $failed
     */
    public function record(int $written, int $stale, array $failed): void;

    public function merge(ChangeSetInterface $other): void;

    /**
     * @return ChangeInterface[]
     */
    public function changes(): array;

    public function written(): int;

    public function stale(): int;

    /**
     * @return array<string, string>
     */
    public function failed(): array;
}
