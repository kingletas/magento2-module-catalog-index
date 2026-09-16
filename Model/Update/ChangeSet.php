<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

/**
 * Every change one refresh made, plus how the store answered.
 */
class ChangeSet
{
    /** @var array<string, Change> */
    private array $changes = [];

    private int $written = 0;

    private int $stale = 0;

    /** @var array<string, string> */
    private array $failed = [];

    public function add(Change $change, string $scope = ''): void
    {
        if (!$change->isNothing()) {
            $this->changes[$scope . ':' . $change->id] = $change;
        }
    }

    /**
     * @param array<string, string> $failed
     */
    public function record(int $written, int $stale, array $failed): void
    {
        $this->written += $written;
        $this->stale += $stale;
        $this->failed += $failed;
    }

    public function merge(ChangeSet $other): void
    {
        $this->changes = array_merge($this->changes, $other->changes);
        $this->record($other->written, $other->stale, $other->failed);
    }

    /**
     * @return Change[]
     */
    public function changes(): array
    {
        return array_values($this->changes);
    }

    public function written(): int
    {
        return $this->written;
    }

    public function stale(): int
    {
        return $this->stale;
    }

    /**
     * @return array<string, string>
     */
    public function failed(): array
    {
        return $this->failed;
    }
}
