<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Index;

use Kingletas\CatalogIndex\Model\Index\IndexDefinition;
use Kingletas\CatalogIndex\Api\Data\IndexFamily;
use Kingletas\CatalogIndex\Test\Support\ShippedConfig;
use PHPUnit\Framework\TestCase;

class IndexDefinitionTest extends TestCase
{
    use ShippedConfig;

    /**
     * Unmapped fields are stored but never indexed, so thousands of attribute codes cannot explode the mapping.
     */
    public function testOnlyTheDeclaredFieldsAreMapped(): void
    {
        $definition = new IndexDefinition(
            $this->config(['index/shards' => '2', 'index/replicas' => '1']),
            ['product' => ['sku' => ['type' => 'keyword']]]
        );

        $product = $definition->for(IndexFamily::Product);

        $this->assertFalse($product['mappings']['dynamic']);
        $this->assertSame(['sku' => ['type' => 'keyword']], $product['mappings']['properties']);
        $this->assertSame(2, $product['settings']['index']['number_of_shards']);
        $this->assertSame(1, $product['settings']['index']['number_of_replicas']);
        $this->assertSame([], $definition->for(IndexFamily::Category)['mappings']['properties']);
    }
}
