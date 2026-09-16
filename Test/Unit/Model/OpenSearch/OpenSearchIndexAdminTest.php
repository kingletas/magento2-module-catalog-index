<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\OpenSearch;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Exception\DocumentStoreException;
use Kingletas\CatalogIndex\Model\OpenSearch\OpenSearchIndexAdmin;
use Kingletas\CatalogIndex\Test\Support\OpenSearchClientDouble;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use OpenSearch\Common\Exceptions\Missing404Exception;
use PHPUnit\Framework\TestCase;

class OpenSearchIndexAdminTest extends TestCase
{
    use OpenSearchClientDouble;

    public function testMovingAnAliasRemovesItFromEveryIndexHoldingItInOneRequest(): void
    {
        $this->answers = [['shop_product_1_a' => [], 'shop_product_1_b' => []], ['acknowledged' => true]];

        $previous = $this->admin()->pointAlias('shop_product_1', 'shop_product_1_c');

        $this->assertSame(['indices.getAlias', 'indices.updateAliases'], array_column($this->sent, 0));
        $actions = $this->sent[1][1]['body']['actions'];
        $this->assertCount(3, $actions);
        $this->assertSame(['add' => ['index' => 'shop_product_1_c', 'alias' => 'shop_product_1']], $actions[2]);
        $this->assertSame('shop_product_1_b', $previous);
    }

    public function testAMissingAliasOrIndexIsNotAnError(): void
    {
        $this->answers = array_fill(0, 4, new Missing404Exception('{"status":404}'));
        $admin = $this->admin();

        $this->assertNull($admin->resolveAlias('shop_product_9'));
        $this->assertSame(0, $admin->count('shop_product_9'));
        $this->assertSame([], $admin->listIndexes('shop_product_9_'));
        $admin->dropIndex('shop_product_9_old');

        $this->assertSame('indices.delete', $this->sent[3][0]);
    }

    public function testRefreshingAMissingIndexIsAnError(): void
    {
        $this->admin()->refresh('shop_product_1_x');
        $this->assertSame(['indices.refresh', ['index' => 'shop_product_1_x']], [
            $this->sent[0][0],
            array_diff_key($this->sent[0][1], ['client' => true]),
        ]);

        $this->answers = [new Missing404Exception('no such index')];
        $this->expectException(DocumentStoreException::class);
        $this->admin()->refresh('shop_product_1_y');
    }

    public function testARefusedIndexCreationThrowsWithTheReason(): void
    {
        $this->answers = [new BadRequest400Exception('resource_already_exists_exception')];

        $this->expectException(DocumentStoreException::class);
        $this->expectExceptionMessage('resource_already_exists_exception');

        $this->admin()->createIndex('shop_product_1_x', ['settings' => []]);
    }

    public function testAnIndexIsCreatedWithItsDefinition(): void
    {
        $this->admin()->createIndex('shop_product_1_x', ['settings' => ['number_of_shards' => 1]]);

        $this->assertSame('shop_product_1_x', $this->sent[0][1]['index']);
        $this->assertSame(['settings' => ['number_of_shards' => 1]], $this->sent[0][1]['body']);
    }

    /**
     * Index names reach the request path, so anything but a plain name is refused before a request is built.
     */
    public function testAPathInAnIndexNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->admin()->dropIndex('_all/../x');
    }

    public function testListingKeepsOnlyIndexesUnderThePrefix(): void
    {
        $this->answers = [[['index' => 'shop_product_1_b'], ['index' => 'other'], ['index' => 'shop_product_1_a']]];

        $this->assertSame(['shop_product_1_a', 'shop_product_1_b'], $this->admin()->listIndexes('shop_product_1_'));
        $this->assertSame('shop_product_1_*', $this->sent[0][1]['index']);
    }

    public function testCountingReadsTheCount(): void
    {
        $this->answers = [['count' => 42]];

        $this->assertSame(42, $this->admin()->count('shop_product_1_x'));
    }

    private function admin(): OpenSearchIndexAdmin
    {
        return new OpenSearchIndexAdmin($this->gateway());
    }
}
