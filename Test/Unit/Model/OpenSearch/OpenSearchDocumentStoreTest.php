<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\OpenSearch;

use InvalidArgumentException;
use Kingletas\CatalogIndex\Model\OpenSearch\OpenSearchDocumentStore;
use Kingletas\CatalogIndex\Model\OpenSearch\ResponseReader;
use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Test\Support\OpenSearchClientDouble;
use PHPUnit\Framework\TestCase;
use stdClass;

class OpenSearchDocumentStoreTest extends TestCase
{
    use OpenSearchClientDouble;

    public function testAWriteSendsEveryDocumentWithItsExternalVersionAsAWrite(): void
    {
        $this->answers = [['items' => [
            ['index' => ['_id' => '1', 'status' => 201]],
            ['index' => ['_id' => '2', 'status' => 201]],
        ]]];

        $result = $this->store()->write('shop_product_1', [
            new Document('1', 55, ['sku' => 'A/B']),
            new Document('2', 55, []),
        ]);

        [$call, $params] = $this->sent[0];
        $this->assertSame('bulk', $call);
        $this->assertSame(
            ['index' => $this->meta('1', 55)],
            $params['body'][0]
        );
        $this->assertSame(['sku' => 'A/B'], $params['body'][1]);
        $this->assertInstanceOf(stdClass::class, $params['body'][3]);
        $this->assertSame(30.0, $params['client']['timeout']);
        $this->assertSame(['1', '2'], $result->getWritten());
    }

    public function testADeleteCarriesTheVersionItDeletesAt(): void
    {
        $this->answers = [['items' => [['delete' => ['_id' => '7', 'status' => 404]]]]];

        $result = $this->store()->delete('shop_product_1', [7], 60);

        $this->assertSame(
            ['delete' => $this->meta('7', 60)],
            $this->sent[0][1]['body'][0]
        );
        $this->assertSame(['7'], $result->getWritten());
    }

    public function testAnEmptyWriteSendsNothing(): void
    {
        $this->store()->write('shop_product_1', []);
        $this->store()->delete('shop_product_1', [], 1);
        $this->assertSame([], $this->store()->fetch([]));

        $this->assertSame([], $this->sent);
    }

    public function testAFetchReadsSeveralIndexesInOneRoundTripWithTheReadTimeout(): void
    {
        $this->answers = [['docs' => [
            ['_index' => 'shop_product_1_20260915', '_id' => '4', 'found' => true, '_version' => 2, '_source' => []],
            ['_index' => 'shop_stock_1_20260915', '_id' => '4', 'found' => false],
        ]]];

        $found = $this->store()->fetch(['shop_product_1' => ['4', '4'], 'shop_stock_1' => ['4']], ['_fp']);

        $this->assertCount(1, $this->sent);
        [$call, $params] = $this->sent[0];
        $this->assertSame('mget', $call);
        $this->assertSame(0.4, $params['client']['timeout']);
        $this->assertCount(2, $params['body']['docs']);
        $this->assertSame(['_fp'], $params['body']['docs'][1]['_source']);
        $this->assertArrayHasKey('4', $found['shop_product_1']);
        $this->assertArrayNotHasKey('shop_stock_1', $found);
    }

    /**
     * Index names reach the request path, so anything but a plain name is refused before a request is built.
     */
    public function testAPathInAnIndexNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->store()->fetch(['_all/../x' => ['1']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(string $id, int $version): array
    {
        return ['_index' => 'shop_product_1', '_id' => $id, 'version' => $version, 'version_type' => 'external_gte'];
    }

    private function store(): OpenSearchDocumentStore
    {
        return new OpenSearchDocumentStore($this->gateway(), new ResponseReader());
    }
}
