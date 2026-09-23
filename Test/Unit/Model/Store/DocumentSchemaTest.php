<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Store;

use Kingletas\CatalogIndex\Model\Store\Document;
use Kingletas\CatalogIndex\Model\Store\DocumentSchema;
use PHPUnit\Framework\TestCase;

class DocumentSchemaTest extends TestCase
{
    public function testAStampedSourceIsCurrent(): void
    {
        $schema = new DocumentSchema();
        $source = $schema->stamp(['sku' => 'SKU-1']);

        $this->assertSame('SKU-1', $source['sku']);
        $this->assertTrue($schema->isCurrent(new Document('1', 1, $source)));
    }

    /**
     * A document written before the schema existed carries no field at all, and has to read as out of date.
     */
    public function testAMissingOrDifferentSchemaIsNotCurrent(): void
    {
        $schema = new DocumentSchema();

        $this->assertFalse($schema->isCurrent(new Document('1', 1, [])));
        $newer = [DocumentSchema::FIELD => DocumentSchema::VERSION + 1];
        $asText = [DocumentSchema::FIELD => (string) DocumentSchema::VERSION];

        $this->assertFalse($schema->isCurrent(new Document('1', 1, $newer)));
        $this->assertFalse($schema->isCurrent(new Document('1', 1, $asText)));
    }
}
