<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Model\Index;

use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Model\Config;

/**
 * Settings and mappings for each family's index.
 */
class IndexDefinition
{
    /**
     * @param array<string, array<string, mixed>> $properties Explicitly mapped fields per family.
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $properties = []
    ) {
    }

    /**
     * Only lookup fields are mapped; everything else is stored and never searched.
     *
     * @return array<string, mixed>
     */
    public function for(IndexFamily $family): array
    {
        return [
            'settings' => [
                'index' => [
                    'number_of_shards' => $this->config->getShards(),
                    'number_of_replicas' => $this->config->getReplicas(),
                    'refresh_interval' => '1s',
                ],
            ],
            'mappings' => [
                'dynamic' => false,
                'properties' => $this->properties[$family->value] ?? [],
            ],
        ];
    }
}
