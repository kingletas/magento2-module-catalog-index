<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Api\Data;

/**
 * One stored document: an id, the version that wrote it, and its source fields.
 *
 * @api
 */
interface DocumentInterface
{
    public const string FINGERPRINTS = '_fp';

    public function getId(): string;

    public function getVersion(): int;

    /**
     * @return array<string, mixed>
     */
    public function getSource(): array;

    public function get(string $field, mixed $default = null): mixed;

    public function getFingerprint(string $group): ?string;
}
