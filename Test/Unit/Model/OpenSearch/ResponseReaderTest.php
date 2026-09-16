<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\OpenSearch;

use Kingletas\CatalogIndex\Model\OpenSearch\ResponseReader;
use PHPUnit\Framework\TestCase;

class ResponseReaderTest extends TestCase
{
    /**
     * A version conflict is the store refusing an older write, which is success for the system and nothing to retry.
     */
    public function testConflictsAreStaleAndOtherErrorsAreFailures(): void
    {
        $result = (new ResponseReader())->writeResult(['items' => [
            ['index' => ['_id' => '1', 'status' => 201]],
            ['index' => ['_id' => '2', 'status' => 409]],
            ['index' => ['_id' => '3', 'status' => 400, 'error' => ['type' => 'mapper_parsing_exception']]],
        ]], 'index');

        $this->assertSame(['1'], $result->written);
        $this->assertSame(['2'], $result->stale);
        $this->assertSame(['3' => 'mapper_parsing_exception'], $result->failed);
    }

    public function testDeletingADocumentThatIsAlreadyGoneIsFine(): void
    {
        $result = (new ResponseReader())->writeResult(
            ['items' => [['delete' => ['_id' => '9', 'status' => 404]]]],
            'delete'
        );

        $this->assertSame(['9'], $result->written);
    }

    /**
     * Reading through an alias answers with the physical index, and callers look results up by the alias.
     */
    public function testDocumentsAreFiledUnderTheNameThatWasRequested(): void
    {
        $documents = (new ResponseReader())->documents(
            [
                ['_index' => 'p_1_20260915', '_id' => '5', 'found' => true, '_version' => 1, '_source' => []],
                ['_index' => 's_1_20260915', '_id' => '5', 'found' => true, '_version' => 1, '_source' => []],
            ],
            ['p_1', 's_1']
        );

        $this->assertSame(['p_1', 's_1'], array_keys($documents));
    }

    public function testOnlyFoundDocumentsAreReturnedByIndexAndId(): void
    {
        $documents = (new ResponseReader())->documents([
            ['_index' => 'p_1', '_id' => '5', 'found' => true, '_version' => 3, '_source' => ['sku' => 'A']],
            ['_index' => 'p_1', '_id' => '6', 'found' => false],
            ['_index' => 'p_1', '_id' => '7', 'error' => ['type' => 'index_not_found_exception']],
        ]);

        $this->assertSame(['5'], array_map('strval', array_keys($documents['p_1'])));
        $this->assertSame(3, $documents['p_1']['5']->getVersion());
        $this->assertSame('A', $documents['p_1']['5']->get('sku'));
    }
}
