<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;

/**
 * A document under construction, with each field assigned to the group that decides what a change purges.
 */
class DocumentDraft
{
    public const string GROUP_LISTING = 'listing';
    public const string GROUP_DETAIL = 'detail';
    public const string GROUP_INTERNAL = 'internal';

    /** @var array<string, mixed> */
    private array $fields = [];

    /** @var array<string, string> Field name to group. */
    private array $groups = [];

    private ?string $exclusion = null;

    /** @var array<int, DateTimeImmutable> */
    private array $refreshAt = [];

    public function __construct(
        public readonly int $id,
        public readonly int $scopeId
    ) {
    }

    public function set(string $field, mixed $value, string $group = self::GROUP_DETAIL): void
    {
        $this->fields[$field] = $value;
        $this->groups[$field] = $group;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return array_key_exists($field, $this->fields) ? $this->fields[$field] : $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, array<string, mixed>> Group name to its fields.
     */
    public function fieldsByGroup(): array
    {
        $grouped = [];

        foreach ($this->fields as $field => $value) {
            $grouped[$this->groups[$field]][$field] = $value;
        }

        ksort($grouped);

        return $grouped;
    }

    public function exclude(string $reason): void
    {
        $this->exclusion ??= $reason;
    }

    public function isExcluded(): bool
    {
        return $this->exclusion !== null;
    }

    public function exclusionReason(): ?string
    {
        return $this->exclusion;
    }

    /**
     * Asks for the document to be rebuilt when a time-bound value starts or stops applying.
     */
    public function refreshAt(DateTimeImmutable $moment): void
    {
        $this->refreshAt[$moment->getTimestamp()] = $moment;
    }

    /**
     * @return array<int, DateTimeImmutable> Keyed by unix time.
     */
    public function refreshMoments(): array
    {
        ksort($this->refreshAt);

        return $this->refreshAt;
    }
}
