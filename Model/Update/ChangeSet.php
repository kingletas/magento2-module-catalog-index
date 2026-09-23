<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use Kingletas\CatalogIndex\Api\Data\ChangeInterface;
use Kingletas\CatalogIndex\Api\Data\ChangeSetInterface;

/**
 * Every change one refresh made, plus how the store answered.
 */
class ChangeSet implements ChangeSetInterface
{
    /** @var array<string, ChangeInterface> Keyed by scope and document id. */
    private array $changes = [];

    private int $written = 0;

    private int $stale = 0;

    /** @var array<string, string> */
    private array $failed = [];

    /**
     * @inheritDoc
     */
    public function add(ChangeInterface $change, string $scope = ''): void
    {
        if (!$change->isNothing()) {
            $this->changes[$scope . ':' . $change->getId()] = $change;
        }
    }

    /**
     * @inheritDoc
     */
    public function record(int $written, int $stale, array $failed): void
    {
        $this->written += $written;
        $this->stale += $stale;
        $this->failed += $failed;
    }

    /**
     * Another implementation's changes are appended, since only this class knows the scope each was added under.
     */
    public function merge(ChangeSetInterface $other): void
    {
        $theirs = $other instanceof self ? $other->changes : $other->changes();
        $this->changes = array_merge($this->changes, $theirs);
        $this->record($other->written(), $other->stale(), $other->failed());
    }

    /**
     * @inheritDoc
     */
    public function changes(): array
    {
        return array_values($this->changes);
    }

    /**
     * @inheritDoc
     */
    public function written(): int
    {
        return $this->written;
    }

    /**
     * @inheritDoc
     */
    public function stale(): int
    {
        return $this->stale;
    }

    /**
     * @inheritDoc
     */
    public function failed(): array
    {
        return $this->failed;
    }
}
