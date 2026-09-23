<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use DateTimeImmutable;
use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;

/**
 * A document under construction, with each field assigned to the group that decides what a change purges.
 */
class DocumentDraft implements DocumentDraftInterface
{
    /** @var array<string, mixed> */
    private array $fields = [];

    /** @var array<string, string> Field name to group. */
    private array $groups = [];

    private ?string $exclusion = null;

    /** @var array<int, DateTimeImmutable> */
    private array $refreshAt = [];

    public function __construct(
        private readonly int $id,
        private readonly int $scopeId
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
    public function getScopeId(): int
    {
        return $this->scopeId;
    }

    /**
     * @inheritDoc
     */
    public function set(string $field, mixed $value, string $group = self::GROUP_DETAIL): void
    {
        $this->fields[$field] = $value;
        $this->groups[$field] = $group;
    }

    /**
     * @inheritDoc
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return array_key_exists($field, $this->fields) ? $this->fields[$field] : $default;
    }

    /**
     * @inheritDoc
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * @inheritDoc
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @inheritDoc
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

    /**
     * @inheritDoc
     */
    public function exclude(string $reason): void
    {
        $this->exclusion ??= $reason;
    }

    /**
     * @inheritDoc
     */
    public function isExcluded(): bool
    {
        return $this->exclusion !== null;
    }

    /**
     * @inheritDoc
     */
    public function exclusionReason(): ?string
    {
        return $this->exclusion;
    }

    /**
     * @inheritDoc
     */
    public function refreshAt(DateTimeImmutable $moment): void
    {
        $this->refreshAt[$moment->getTimestamp()] = $moment;
    }

    /**
     * @inheritDoc
     */
    public function refreshMoments(): array
    {
        ksort($this->refreshAt);

        return $this->refreshAt;
    }
}
