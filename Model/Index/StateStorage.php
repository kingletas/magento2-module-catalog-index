<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Index;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Small named records: which build is live, and where each sweep stopped.
 */
class StateStorage
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
        private readonly string $table = 'kingletas_catalog_index_state'
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName($this->table), ['state_value'])
                ->where('state_key = ?', $key)
        );

        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = $this->json->unserialize($value);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void
    {
        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName($this->table),
            ['state_key' => $key, 'state_value' => (string) $this->json->serialize($value)],
            ['state_value']
        );
    }
}
