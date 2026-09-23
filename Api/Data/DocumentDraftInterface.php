<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

use DateTimeImmutable;

/**
 * A document under construction, with each field assigned to the group that decides what a change purges.
 *
 * @api
 */
interface DocumentDraftInterface
{
    public const string GROUP_LISTING = 'listing';
    public const string GROUP_DETAIL = 'detail';
    public const string GROUP_INTERNAL = 'internal';

    public function getId(): int;

    public function getScopeId(): int;

    public function set(string $field, mixed $value, string $group = self::GROUP_DETAIL): void;

    public function get(string $field, mixed $default = null): mixed;

    public function has(string $field): bool;

    /**
     * @return array<string, mixed>
     */
    public function fields(): array;

    /**
     * @return array<string, array<string, mixed>> Group name to its fields.
     */
    public function fieldsByGroup(): array;

    /**
     * Keeps the product out of the index; the first reason given wins.
     */
    public function exclude(string $reason): void;

    public function isExcluded(): bool;

    public function exclusionReason(): ?string;

    /**
     * Asks for the document to be rebuilt when a time-bound value starts or stops applying.
     */
    public function refreshAt(DateTimeImmutable $moment): void;

    /**
     * @return array<int, DateTimeImmutable> Keyed by unix time.
     */
    public function refreshMoments(): array;
}
