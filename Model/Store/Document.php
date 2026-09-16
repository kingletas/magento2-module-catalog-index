<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Store;

use Kingletas\CatalogIndex\Api\Data\DocumentInterface;

/**
 * An immutable stored document.
 */
class Document implements DocumentInterface
{
    /**
     * @param array<string, mixed> $source
     */
    public function __construct(
        private readonly string $id,
        private readonly int $version,
        private readonly array $source
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @inheritDoc
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * @inheritDoc
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return array_key_exists($field, $this->source) ? $this->source[$field] : $default;
    }

    /**
     * @inheritDoc
     */
    public function getFingerprint(string $group): ?string
    {
        $fingerprints = $this->source[self::FINGERPRINTS] ?? [];
        $value = is_array($fingerprints) ? ($fingerprints[$group] ?? null) : null;

        return is_string($value) ? $value : null;
    }
}
