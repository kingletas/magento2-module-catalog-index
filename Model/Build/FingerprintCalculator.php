<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Build;

use Kingletas\CatalogIndex\Api\Data\DocumentDraftInterface;
use Kingletas\CatalogIndex\Api\Data\DocumentInterface;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Store\DocumentSchema;

/**
 * Turns a draft into a document carrying one fingerprint per field group.
 */
class FingerprintCalculator
{
    public function __construct(
        private readonly DocumentSchema $schema = new DocumentSchema()
    ) {
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function fingerprint(array $fields): string
    {
        return sha1((string) json_encode($this->canonical($fields), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function document(DocumentDraftInterface $draft, int $version): DocumentInterface
    {
        $fingerprints = [];

        foreach ($draft->fieldsByGroup() as $group => $fields) {
            $fingerprints[$group] = $this->fingerprint($fields);
        }

        $source = $draft->fields();
        $source[DocumentInterface::FINGERPRINTS] = $fingerprints;

        return new Document((string) $draft->getId(), $version, $this->schema->stamp($source));
    }

    /**
     * Sorts associative keys at every depth, so equal data always hashes the same.
     */
    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_float($value) ? round($value, 6) : $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }
}
