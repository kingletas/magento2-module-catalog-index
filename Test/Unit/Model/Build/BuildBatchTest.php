<?php
/**
 * @package   Kingletas_CatalogIndex
 * @copyright Copyright (c) the Kingletas modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Kingletas\CatalogIndex\Test\Unit\Model\Build;

use Kingletas\CatalogIndex\Model\Build\BuildBatch;
use Kingletas\CatalogIndex\Model\Build\BuildContext;
use Kingletas\CatalogIndex\Model\Store\Document;
use PHPUnit\Framework\TestCase;

class BuildBatchTest extends TestCase
{
    public function testDocumentIdsAreListedInOrder(): void
    {
        $batch = new BuildBatch(5, [new Document('3', 5, []), new Document('9', 5, [])], [4]);

        $this->assertSame(['3', '9'], $batch->documentIds());
        $this->assertSame([4], $batch->removedIds);
    }

    public function testAContextCarriesItsScope(): void
    {
        $context = new BuildContext(2, 1, 77, new \DateTimeImmutable('2026-09-15'), 'Europe/Madrid');

        $this->assertSame(2, $context->storeId);
        $this->assertSame('Europe/Madrid', $context->timezone);
    }
}
