<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Index;

use Kingletas\CatalogIndex\Model\Index\StateStorage;
use Kingletas\CatalogIndex\Test\Support\StubbedDatabase;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

class StateStorageTest extends TestCase
{
    use StubbedDatabase;

    public function testAStoredValueReadsBackAsTheArrayWritten(): void
    {
        $this->answers['kingletas_catalog_index_state'] = '{"index":"a_1"}';
        $storage = new StateStorage($this->resourceConnection(), new Json());

        $storage->set('build:product:1', ['index' => 'a_1']);

        $this->assertSame(['index' => 'a_1'], $storage->get('build:product:1'));
        $this->assertSame('insertOnDuplicate', $this->writes[0][0]);
        $this->assertSame('{"index":"a_1"}', $this->writes[0][1][1]['state_value']);
    }

    public function testAMissingKeyIsNull(): void
    {
        $this->assertNull((new StateStorage($this->resourceConnection(), new Json()))->get('nothing'));
    }
}
