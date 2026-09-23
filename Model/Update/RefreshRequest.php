<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Update;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;

/**
 * A request to rebuild some documents, and why, which decides the lane it may take.
 */
class RefreshRequest
{
    public const string REASON_ORDER = 'order';
    public const string REASON_PRODUCT_SAVED = 'product_saved';
    public const string REASON_CATEGORY_SAVED = 'category_saved';
    public const string REASON_ATTRIBUTE_SAVED = 'attribute_saved';
    public const string REASON_SCHEDULE = 'schedule';
    public const string REASON_RESERVATION = 'reservation';
    public const string REASON_FULL = 'full';
    public const string REASON_MANUAL = 'manual';

    /** @var int[] */
    public readonly array $ids;

    /**
     * @param array<int|string> $ids
     */
    public function __construct(
        public readonly IndexFamily $family,
        array $ids,
        public readonly string $reason
    ) {
        $clean = array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0);
        $clean = array_values(array_unique($clean));
        sort($clean);
        $this->ids = $clean;

        if ($this->ids === [] && $this->reason !== self::REASON_FULL) {
            throw new InvalidArgumentException(
                (string) __('A refresh request needs at least one id unless it is a full rebuild.')
            );
        }
    }

    public function isFull(): bool
    {
        return $this->reason === self::REASON_FULL;
    }

    /**
     * Saves are already in the change log, so a store that relies on it can skip publishing them.
     */
    public function isInChangelog(): bool
    {
        return in_array($this->reason, [self::REASON_PRODUCT_SAVED, self::REASON_CATEGORY_SAVED], true);
    }

    /**
     * Nothing raised while a shopper waits for checkout is ever processed in that request.
     */
    public function mayRunInline(): bool
    {
        return $this->reason !== self::REASON_ORDER && !$this->isFull();
    }

    /**
     * @return array{family: string, ids: int[], reason: string}
     */
    public function toArray(): array
    {
        return ['family' => $this->family->value, 'ids' => $this->ids, 'reason' => $this->reason];
    }
}
